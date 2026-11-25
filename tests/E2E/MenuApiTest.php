<?php

declare(strict_types=1);

namespace Goomer\Tests\E2E;

final class MenuApiTest extends ApiTestCase
{
    public function test_public_menu_on_wednesday_happy_hour(): void
    {
        $token = $this->login();
        $chopp = $this->createProduct($token, ['name' => 'Chopp 300 ml', 'price' => 1400, 'category' => 'bebidas']);
        $this->createProduct($token, ['name' => 'Bolinho', 'price' => 3200, 'category' => 'entradas']);
        $this->createProduct($token, ['name' => 'Secreto', 'price' => 9900, 'category' => 'pratos_principais', 'visible' => false]);
        $this->createProduct($token, ['name' => 'Feijoada', 'price' => 6500, 'category' => 'pratos_principais',
            'availability' => [['weekday' => 6, 'start' => '11:00', 'end' => '16:00']]]);
        $this->request('POST', '/api/v1/promotions', ['productId' => $chopp, 'description' => 'Happy hour: chopp pela metade do preço',
            'price' => 700, 'windows' => [['weekday' => 3, 'start' => '18:00', 'end' => '20:00']]], $token);

        $response = $this->request('GET', '/api/v1/restaurants/1/menu');

        self::assertSame(200, $response->getStatusCode());
        self::assertSame([
            'restaurant' => ['id' => 1, 'name' => 'Bar do João', 'timezone' => 'America/Sao_Paulo'],
            'generatedAt' => '2026-12-02T18:30:00-03:00',
            'categories' => [
                ['key' => 'entradas', 'name' => 'Entradas', 'products' => [
                    ['id' => $chopp + 1, 'name' => 'Bolinho', 'price' => 3200, 'promotion' => null],
                ]],
                ['key' => 'bebidas', 'name' => 'Bebidas', 'products' => [
                    ['id' => $chopp, 'name' => 'Chopp 300 ml', 'price' => 1400, 'promotion' => [
                        'description' => 'Happy hour: chopp pela metade do preço', 'price' => 700, 'endsAt' => '20:00',
                    ]],
                ]],
            ],
        ], $this->json($response));
    }

    public function test_menu_after_happy_hour_and_unknown_restaurant(): void
    {
        $token = $this->login();
        $chopp = $this->createProduct($token, ['name' => 'Chopp', 'price' => 1400, 'category' => 'bebidas']);
        $this->request('POST', '/api/v1/promotions', ['productId' => $chopp, 'description' => 'HH', 'price' => 700,
            'windows' => [['weekday' => 3, 'start' => '18:00', 'end' => '20:00']]], $token);

        $this->clock->set('2026-12-02T23:00:00Z');
        $menu = $this->json($this->request('GET', '/api/v1/restaurants/1/menu'));
        self::assertSame(
            [['key' => 'bebidas', 'name' => 'Bebidas', 'products' => [
                ['id' => $chopp, 'name' => 'Chopp', 'price' => 1400, 'promotion' => null],
            ]]],
            $menu['categories'],
        );

        self::assertSame(404, $this->request('GET', '/api/v1/restaurants/99/menu')->getStatusCode());
        self::assertSame(404, $this->request('GET', '/api/v1/restaurants/x/menu')->getStatusCode());
    }
}
