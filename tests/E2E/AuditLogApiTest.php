<?php

declare(strict_types=1);

namespace Goomer\Tests\E2E;

use Goomer\User\Application\CreateUserUseCase;

final class AuditLogApiTest extends ApiTestCase
{
    /**
     * @return array<array-key, mixed>
     */
    private function logs(string $token, int $userId, string $query = 'dateIni=2026-12-02', int $status = 200): array
    {
        $response = $this->request('GET', "/api/v1/users/{$userId}/audit-logs?{$query}", null, $token);
        self::assertSame($status, $response->getStatusCode(), (string) $response->getBody());

        return $this->json($response);
    }

    /**
     * @param array<array-key, mixed> $body
     *
     * @return list<string>
     */
    private static function actions(array $body): array
    {
        self::assertIsArray($body['data']);

        return array_map(static fn(mixed $item): string => is_array($item) && is_string($item['action']) ? $item['action'] : '', array_values($body['data']));
    }

    public function test_product_and_promotion_writes_are_in_the_log(): void
    {
        $token = $this->login();
        $product = $this->createProduct($token, ['name' => 'Chopp', 'price' => 1400, 'category' => 'bebidas']);
        $this->request('PUT', "/api/v1/products/{$product}", ['name' => 'Chopp 500 ml', 'price' => 1400, 'category' => 'bebidas'], $token);
        $this->request('PATCH', "/api/v1/products/{$product}/visibility", ['visible' => false], $token);
        $promotion = $this->json($this->request('POST', '/api/v1/promotions', [
            'productId' => $product, 'description' => 'Happy hour', 'price' => 700,
            'windows' => [['weekday' => 3, 'start' => '18:00', 'end' => '20:00']],
        ], $token))['id'] ?? null;
        self::assertIsInt($promotion);
        $this->request('PUT', "/api/v1/promotions/{$promotion}", [
            'description' => 'Happy hour', 'price' => 650, 'windows' => [['weekday' => 3, 'start' => '18:00', 'end' => '20:00']],
        ], $token);
        self::assertSame(204, $this->request('DELETE', "/api/v1/promotions/{$promotion}", null, $token)->getStatusCode());
        self::assertSame(204, $this->request('DELETE', "/api/v1/products/{$product}", null, $token)->getStatusCode());

        $body = $this->logs($token, $this->adminId);
        self::assertSame([
            'product.deleted', 'promotion.deleted', 'promotion.updated', 'promotion.created',
            'product.visibility_changed', 'product.updated', 'product.created', 'user.created',
        ], self::actions($body), 'mais recentes primeiro (mesmo instante: pelo id)');
        self::assertSame(['id' => $this->adminId, 'email' => self::ADMIN_EMAIL], $body['user']);
        self::assertSame(['page' => 1, 'perPage' => 50, 'total' => 8], $body['meta']);

        $updated = ((array) $body['data'])[5];
        self::assertIsArray($updated);
        self::assertSame([
            'id' => $updated['id'] ?? null,
            'action' => 'product.updated',
            'entityType' => 'product',
            'entityId' => $product,
            'changes' => ['before' => ['name' => 'Chopp'], 'after' => ['name' => 'Chopp 500 ml']],
            'ip' => '10.0.0.1',
            'createdAt' => '2026-12-02T18:30:00-03:00',
        ], $updated);
        self::assertSame(['before' => ['price' => 700], 'after' => ['price' => 650]], ((array) ((array) $body['data'])[2])['changes'] ?? null);
    }

    public function test_period_filter_with_and_without_date_end(): void
    {
        $token = $this->login();
        $this->createProduct($token, ['name' => 'Chopp', 'price' => 1400, 'category' => 'bebidas']);
        $this->clock->advance('+1 day');
        $token = $this->login();
        $this->createProduct($token, ['name' => 'Água', 'price' => 500, 'category' => 'bebidas']);

        self::assertSame(3, ((array) $this->logs($token, $this->adminId)['meta'])['total'] ?? null, 'sem dateEnd: até hoje');
        self::assertSame(2, ((array) $this->logs($token, $this->adminId, 'dateIni=2026-12-02&dateEnd=2026-12-02')['meta'])['total'] ?? null);
        self::assertSame(['product.created'], self::actions($this->logs($token, $this->adminId, 'dateIni=2026-12-03')));
        self::assertSame([], self::actions($this->logs($token, $this->adminId, 'dateIni=2026-12-01&dateEnd=2026-12-01')));
        self::assertCount(1, (array) $this->logs($token, $this->adminId, 'dateIni=2026-12-02&page=2&perPage=2')['data']);
    }

    public function test_errors_401_404_and_422(): void
    {
        self::assertSame(401, $this->request('GET', "/api/v1/users/{$this->adminId}/audit-logs?dateIni=2026-12-02")->getStatusCode());

        $token = $this->login();
        $users = $this->container->get(CreateUserUseCase::class);
        self::assertInstanceOf(CreateUserUseCase::class, $users);
        $foreign = $users->bootstrap(2, 'Outro', 'outro@rest.com', 'senha-forte');

        $this->logs($token, $foreign->id, 'dateIni=2026-12-02', 404);
        $this->logs($token, 999, 'dateIni=2026-12-02', 404);

        foreach ([
            '' => 'dateIni',
            'dateIni=02/12/2026' => 'dateIni',
            'dateIni=2026-02-30' => 'dateIni',
            'dateIni=2026-12-02&dateEnd=ontem' => 'dateEnd',
            'dateIni=2026-12-02&dateEnd=2026-12-01' => 'dateEnd',
        ] as $query => $field) {
            $problem = $this->logs($token, $this->adminId, $query, 422);
            self::assertArrayHasKey($field, (array) $problem['errors'], $query);
        }
    }

    public function test_deleted_user_still_has_log_and_no_secrets_are_logged(): void
    {
        $admin = $this->login();
        $created = $this->request('POST', '/api/v1/users', ['name' => 'Bia', 'email' => 'bia@bar.com', 'password' => 'senha-da-bia'], $admin);
        $bia = $this->json($created)['id'] ?? null;
        self::assertIsInt($bia);

        $biaToken = $this->login('bia@bar.com', 'senha-da-bia');
        $this->createProduct($biaToken, ['name' => 'Pudim', 'price' => 1800, 'category' => 'sobremesas']);
        self::assertSame(204, $this->request('DELETE', "/api/v1/users/{$bia}", null, $admin)->getStatusCode());

        $body = $this->logs($admin, $bia);
        self::assertSame(['id' => $bia, 'email' => 'bia@bar.com'], $body['user']);
        self::assertSame(['product.created'], self::actions($body));

        $adminLog = $this->logs($admin, $this->adminId);
        self::assertSame(['user.deleted', 'user.created', 'user.created'], self::actions($adminLog));
        $raw = json_encode($adminLog, JSON_THROW_ON_ERROR);
        self::assertStringNotContainsString('senha-da-bia', $raw);
        self::assertStringNotContainsString('argon2', $raw);
        self::assertStringNotContainsString($biaToken, $raw);
    }
}
