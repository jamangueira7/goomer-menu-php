<?php

declare(strict_types=1);

namespace Goomer\Tests\E2E;

final class ProductsAndPromotionsApiTest extends ApiTestCase
{
    public function test_protected_routes_require_valid_token(): void
    {
        foreach (['/api/v1/products', '/api/v1/promotions', '/api/v1/users'] as $uri) {
            $response = $this->request('GET', $uri);
            self::assertSame(401, $response->getStatusCode(), $uri);
            self::assertSame('application/problem+json', $response->getHeaderLine('Content-Type'));
            self::assertSame('Bearer', $response->getHeaderLine('WWW-Authenticate'));
        }
        self::assertSame(401, $this->request('GET', '/api/v1/products', null, 'lixo')->getStatusCode());

        $token = $this->login();
        $this->clock->advance('+61 minutes');
        self::assertSame(401, $this->request('GET', '/api/v1/products', null, $token)->getStatusCode(), 'token vencido');
    }

    public function test_login_with_wrong_password_is_401(): void
    {
        $response = $this->request('POST', '/api/v1/auth/login', ['email' => self::ADMIN_EMAIL, 'password' => 'errada']);
        self::assertSame(401, $response->getStatusCode());
        self::assertSame(422, $this->request('POST', '/api/v1/auth/login', ['email' => 1])->getStatusCode());
        self::assertSame(400, $this->request('POST', '/api/v1/auth/login', '{não é json')->getStatusCode());
    }

    public function test_product_crud(): void
    {
        $token = $this->login();

        $create = $this->request('POST', '/api/v1/products', [
            'name' => 'Chopp 300 ml', 'price' => 1400, 'category' => 'bebidas',
            'availability' => [['weekday' => 3, 'start' => '17:00', 'end' => '23:00']],
        ], $token);
        self::assertSame(201, $create->getStatusCode());
        $product = $this->json($create);
        self::assertIsInt($product['id']);
        self::assertSame('/api/v1/products/' . $product['id'], $create->getHeaderLine('Location'));
        self::assertTrue($product['visible']);

        $uri = '/api/v1/products/' . $product['id'];
        self::assertSame('Chopp 300 ml', $this->json($this->request('GET', $uri, null, $token))['name']);

        $put = $this->request('PUT', $uri, ['name' => 'Chopp 500 ml', 'price' => 1900, 'category' => 'bebidas'], $token);
        self::assertSame(200, $put->getStatusCode());
        self::assertSame([], $this->json($put)['availability']);

        $hide = $this->request('PATCH', $uri . '/visibility', ['visible' => false], $token);
        self::assertFalse($this->json($hide)['visible']);

        $list = $this->json($this->request('GET', '/api/v1/products?visible=false&category=bebidas', null, $token));
        self::assertSame(['page' => 1, 'perPage' => 20, 'total' => 1], $list['meta']);

        self::assertSame(204, $this->request('DELETE', $uri, null, $token)->getStatusCode());
        self::assertSame(404, $this->request('GET', $uri, null, $token)->getStatusCode());
        self::assertSame(404, $this->request('GET', '/api/v1/products/abc', null, $token)->getStatusCode());
    }

    public function test_product_validation_errors_are_problem_details(): void
    {
        $token = $this->login();

        $response = $this->request('POST', '/api/v1/products', ['name' => 'X', 'price' => '10', 'category' => 'bebidas'], $token);
        self::assertSame(422, $response->getStatusCode());
        self::assertSame('application/problem+json', $response->getHeaderLine('Content-Type'));
        $problem = $this->json($response);
        self::assertSame(422, $problem['status']);
        self::assertArrayHasKey('price', (array) $problem['errors']);

        $badTime = $this->request('POST', '/api/v1/products', [
            'name' => 'X', 'price' => 100, 'category' => 'bebidas',
            'availability' => [['weekday' => 1, 'start' => '10:10', 'end' => '11:00']],
        ], $token);
        self::assertSame(422, $badTime->getStatusCode());
        self::assertSame(422, $this->request('POST', '/api/v1/products', ['name' => 'X', 'price' => 100, 'category' => 'lanches'], $token)->getStatusCode());
        self::assertSame(422, $this->request('GET', '/api/v1/products?visible=talvez', null, $token)->getStatusCode());
    }

    public function test_products_of_other_restaurant_are_invisible(): void
    {
        $this->db->execute("INSERT INTO products (restaurant_id, name, price_cents, category) VALUES (2, 'Lámen', 4500, 'pratos_principais')");
        $foreignId = $this->db->fetchInt('SELECT id FROM products WHERE restaurant_id = 2');
        $token = $this->login();

        self::assertSame(404, $this->request('GET', "/api/v1/products/{$foreignId}", null, $token)->getStatusCode());
        self::assertSame(404, $this->request('DELETE', "/api/v1/products/{$foreignId}", null, $token)->getStatusCode());
        self::assertSame(
            ['page' => 1, 'perPage' => 20, 'total' => 0],
            $this->json($this->request('GET', '/api/v1/products', null, $token))['meta'],
        );
    }

    public function test_reorder_products(): void
    {
        $token = $this->login();
        $a = $this->createProduct($token, ['name' => 'A', 'price' => 100, 'category' => 'bebidas']);
        $b = $this->createProduct($token, ['name' => 'B', 'price' => 100, 'category' => 'bebidas']);

        self::assertSame(204, $this->request('PUT', '/api/v1/products/order', ['ids' => [$b, $a]], $token)->getStatusCode());
        $data = $this->json($this->request('GET', '/api/v1/products', null, $token))['data'];
        self::assertIsArray($data);
        self::assertSame([$b, $a], array_column($data, 'id'));
        self::assertSame(422, $this->request('PUT', '/api/v1/products/order', ['ids' => [999]], $token)->getStatusCode());
    }

    public function test_promotion_rules_and_crud(): void
    {
        $token = $this->login();
        $chopp = $this->createProduct($token, ['name' => 'Chopp', 'price' => 1400, 'category' => 'bebidas']);
        $happyHour = ['productId' => $chopp, 'description' => 'Happy hour', 'price' => 700,
            'windows' => [['weekday' => 3, 'start' => '18:00', 'end' => '20:00']]];

        $create = $this->request('POST', '/api/v1/promotions', $happyHour, $token);
        self::assertSame(201, $create->getStatusCode(), (string) $create->getBody());
        $id = $this->json($create)['id'];
        self::assertIsInt($id);
        self::assertSame("/api/v1/promotions/{$id}", $create->getHeaderLine('Location'));

        $overlap = $this->request('POST', '/api/v1/promotions', [...$happyHour, 'windows' => [['weekday' => 3, 'start' => '19:00', 'end' => '21:00']]], $token);
        self::assertSame(409, $overlap->getStatusCode());

        self::assertSame(422, $this->request('POST', '/api/v1/promotions', [...$happyHour, 'price' => 1400], $token)->getStatusCode(), 'preço >= normal');
        self::assertSame(422, $this->request('POST', '/api/v1/promotions', [...$happyHour, 'windows' => [['weekday' => 4, 'start' => '18:00', 'end' => '18:10']]], $token)->getStatusCode(), '15 min');
        self::assertSame(422, $this->request('POST', '/api/v1/promotions', [...$happyHour, 'windows' => [['weekday' => 5, 'start' => '22:00', 'end' => '02:00']]], $token)->getStatusCode(), 'meia-noite');
        self::assertSame(404, $this->request('POST', '/api/v1/promotions', [...$happyHour, 'productId' => 999], $token)->getStatusCode());

        $put = $this->request('PUT', "/api/v1/promotions/{$id}", ['description' => 'Happy hour estendido', 'price' => 650,
            'windows' => [['weekday' => 3, 'start' => '17:00', 'end' => '20:00']]], $token);
        self::assertSame(200, $put->getStatusCode(), (string) $put->getBody());
        self::assertSame('Happy hour estendido', $this->json($this->request('GET', "/api/v1/promotions/{$id}", null, $token))['description']);
        self::assertCount(1, (array) $this->json($this->request('GET', "/api/v1/promotions?productId={$chopp}", null, $token))['data']);

        self::assertSame(204, $this->request('DELETE', "/api/v1/promotions/{$id}", null, $token)->getStatusCode());
        self::assertSame(404, $this->request('GET', "/api/v1/promotions/{$id}", null, $token)->getStatusCode());
    }

    public function test_deleting_product_deletes_its_promotions(): void
    {
        $token = $this->login();
        $chopp = $this->createProduct($token, ['name' => 'Chopp', 'price' => 1400, 'category' => 'bebidas']);
        $this->request('POST', '/api/v1/promotions', ['productId' => $chopp, 'description' => 'HH', 'price' => 700,
            'windows' => [['weekday' => 3, 'start' => '18:00', 'end' => '20:00']]], $token);

        $this->request('DELETE', "/api/v1/products/{$chopp}", null, $token);

        self::assertSame([], $this->json($this->request('GET', '/api/v1/promotions', null, $token))['data']);
    }

    public function test_unknown_route_and_method(): void
    {
        self::assertSame(404, $this->request('GET', '/api/v1/nada')->getStatusCode());
        self::assertSame(405, $this->request('DELETE', '/api/v1/auth/login')->getStatusCode());
    }
}
