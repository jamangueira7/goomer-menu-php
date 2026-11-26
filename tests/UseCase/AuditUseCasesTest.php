<?php

declare(strict_types=1);

namespace Goomer\Tests\UseCase;

use Goomer\Auth\Application\ForgotPasswordUseCase;
use Goomer\Auth\Application\ResetPasswordUseCase;
use Goomer\Menu\Domain\Restaurant;
use Goomer\Menu\Domain\RestaurantNotFound;
use Goomer\Product\Application\ChangeProductVisibilityUseCase;
use Goomer\Product\Application\CreateProductUseCase;
use Goomer\Product\Application\DeleteProductUseCase;
use Goomer\Product\Application\ProductData;
use Goomer\Product\Application\ReorderProductsUseCase;
use Goomer\Product\Application\UpdateProductUseCase;
use Goomer\Product\Domain\ProductRepository;
use Goomer\Promotion\Application\CreatePromotionUseCase;
use Goomer\Promotion\Application\DeletePromotionUseCase;
use Goomer\Promotion\Application\PromotionData;
use Goomer\Promotion\Application\UpdatePromotionUseCase;
use Goomer\Promotion\Domain\OverlappingPromotion;
use Goomer\Shared\Application\Audit\AuditEntry;
use Goomer\Shared\Application\Audit\Auditor;
use Goomer\Shared\Domain\Exception\ValidationError;
use Goomer\Shared\Domain\Timezone;
use Goomer\Tests\Support\AuthFakes;
use Goomer\Tests\Support\FakeHasher;
use Goomer\Tests\Support\FixedClock;
use Goomer\Tests\Support\InMemoryAudit;
use Goomer\Tests\Support\InMemoryMenu;
use Goomer\Tests\Support\InMemoryProductRepository;
use Goomer\Tests\Support\InMemoryPromotionRepository;
use Goomer\Tests\Support\InMemoryUserRepository;
use Goomer\User\Application\CreateUserUseCase;
use Goomer\User\Application\DeleteUserUseCase;
use Goomer\User\Application\ListUserAuditLogsUseCase;
use Goomer\User\Domain\UserNotFound;
use PHPUnit\Framework\TestCase;

final class AuditUseCasesTest extends TestCase
{
    private FixedClock $clock;
    private InMemoryAudit $audit;
    private InMemoryProductRepository $products;
    private InMemoryPromotionRepository $promotions;
    private InMemoryUserRepository $users;

    protected function setUp(): void
    {
        $this->clock = new FixedClock('2026-11-25T21:31:04Z'); // 18:31:04 em São Paulo
        $this->audit = new InMemoryAudit($this->clock);
        $this->products = new InMemoryProductRepository();
        $this->promotions = new InMemoryPromotionRepository($this->products);
        $this->users = new InMemoryUserRepository();
    }

    private function createProduct(string $name = 'Chopp', int $price = 1400): int
    {
        return (new CreateProductUseCase($this->products, $this->audit, $this->audit->auditor()))
            ->execute(InMemoryAudit::actor(), new ProductData($name, $price, 'bebidas'))->id;
    }

    private function createPromotion(int $productId, string $start = '18:00', string $end = '20:00'): int
    {
        return (new CreatePromotionUseCase($this->products, $this->promotions, $this->audit, $this->audit->auditor()))
            ->execute(InMemoryAudit::actor(), $productId, new PromotionData('Happy hour', 700, [['weekday' => 3, 'start' => $start, 'end' => $end]]))->id;
    }

    private function listLogs(): ListUserAuditLogsUseCase
    {
        $menu = new InMemoryMenu($this->products, $this->promotions);
        $menu->addRestaurant(new Restaurant(1, 'Bar do João', Timezone::fromString('America/Sao_Paulo')));

        return new ListUserAuditLogsUseCase($this->users, $this->audit, $menu, $this->clock);
    }

    public function test_product_writes_record_who_what_and_when(): void
    {
        $id = $this->createProduct();
        $created = $this->audit->last();
        self::assertSame(['product.created', 'product', $id], [$created->action, $created->entityType, $created->entityId]);
        self::assertSame([1, 1, 'admin@bar.com', '10.0.0.1'], [$created->restaurantId, $created->actorUserId, $created->actorEmail, $created->ip]);
        self::assertSame('2026-11-25T21:31:04+00:00', $created->createdAt->format(\DATE_ATOM), 'hora do Clock');
        self::assertSame(['name' => 'Chopp', 'price' => 1400], array_intersect_key((array) ($created->changes['after'] ?? []), ['name' => 1, 'price' => 1]));

        (new UpdateProductUseCase($this->products, $this->audit, $this->audit->auditor()))
            ->execute(InMemoryAudit::actor(), $id, new ProductData('Chopp', 1900, 'bebidas'));
        self::assertSame(['before' => ['price' => 1400], 'after' => ['price' => 1900]], $this->audit->last()->changes, 'só o que mudou');

        (new ChangeProductVisibilityUseCase($this->products, $this->audit, $this->audit->auditor()))->execute(InMemoryAudit::actor(), $id, false);
        self::assertSame(['before' => ['visible' => true], 'after' => ['visible' => false]], $this->audit->last()->changes);

        $other = $this->createProduct('Água', 500);
        (new ReorderProductsUseCase($this->products, $this->audit, $this->audit->auditor()))->execute(InMemoryAudit::actor(), [$other, $id]);
        self::assertNull($this->audit->last()->entityId);
        self::assertSame(['after' => ['ids' => [$other, $id]]], $this->audit->last()->changes);

        $this->createPromotion($id);
        (new DeleteProductUseCase($this->products, $this->promotions, $this->audit, $this->audit->auditor()))->execute(InMemoryAudit::actor(), $id);
        $deleted = $this->audit->last();
        self::assertSame('product.deleted', $deleted->action);
        self::assertSame(1, ((array) $deleted->changes['before'])['cascadedPromotions'] ?? null);

        self::assertSame(
            ['product.created', 'product.updated', 'product.visibility_changed', 'product.created', 'product.reordered', 'promotion.created', 'product.deleted'],
            $this->audit->actions(),
        );
        self::assertSame(7, $this->audit->transactions, 'cada escrita na sua transação');
    }

    public function test_promotion_writes_are_audited_with_windows(): void
    {
        $id = $this->createPromotion($this->createProduct());
        self::assertSame([['weekday' => 3, 'start' => '18:00', 'end' => '20:00']], ((array) $this->audit->last()->changes['after'])['windows'] ?? null);

        (new UpdatePromotionUseCase($this->products, $this->promotions, $this->audit, $this->audit->auditor()))->execute(
            InMemoryAudit::actor(),
            $id,
            new PromotionData('Happy hour', 600, [['weekday' => 4, 'start' => '18:00', 'end' => '20:00']]),
        );
        self::assertSame([
            'before' => ['price' => 700, 'windows' => [['weekday' => 3, 'start' => '18:00', 'end' => '20:00']]],
            'after' => ['price' => 600, 'windows' => [['weekday' => 4, 'start' => '18:00', 'end' => '20:00']]],
        ], $this->audit->last()->changes);

        (new DeletePromotionUseCase($this->promotions, $this->audit, $this->audit->auditor()))->execute(InMemoryAudit::actor(), $id);
        self::assertSame(['promotion', $id], [$this->audit->last()->entityType, $this->audit->last()->entityId]);
        self::assertSame('Happy hour', ((array) $this->audit->last()->changes['before'])['description'] ?? null);
        self::assertSame(['product.created', 'promotion.created', 'promotion.updated', 'promotion.deleted'], $this->audit->actions());
    }

    public function test_user_writes_never_log_password_or_hash(): void
    {
        $create = new CreateUserUseCase($this->users, new FakeHasher(), $this->audit, $this->audit->auditor());
        $admin = $create->bootstrap(1, 'Admin', 'admin@bar.com', 'senha-forte');
        $first = $this->audit->last();
        self::assertSame([$admin->id, $admin->id, null], [$first->actorUserId, $first->entityId, $first->ip], 'no bootstrap o ator é o próprio usuário');

        $bia = $create->execute(InMemoryAudit::actor($admin->id), 'Bia', 'bia@bar.com', 'outra-senha');
        self::assertSame(['after' => ['name' => 'Bia', 'email' => 'bia@bar.com']], $this->audit->last()->changes);

        (new DeleteUserUseCase($this->users, $this->audit, $this->audit->auditor()))->execute(InMemoryAudit::actor($admin->id), $bia->id);
        self::assertSame(['user.deleted', $bia->id], [$this->audit->last()->action, $this->audit->last()->entityId]);

        $everything = json_encode(array_map(static fn(AuditEntry $e): string => $e->changesJson(), $this->audit->entries), JSON_THROW_ON_ERROR);
        self::assertStringNotContainsString('senha', $everything);
        self::assertStringNotContainsString('hashed', $everything);
    }

    public function test_password_reset_is_audited_with_user_as_actor(): void
    {
        $fakes = new AuthFakes();
        $admin = (new CreateUserUseCase($this->users, new FakeHasher(), $this->audit, $this->audit->auditor()))
            ->bootstrap(1, 'Admin', 'admin@bar.com', 'senha-forte');
        $forgot = new ForgotPasswordUseCase($this->users, $fakes, $fakes, $fakes, $this->clock, $this->audit, $this->audit->auditor(), 'http://app/reset');

        $forgot->execute('ninguem@bar.com', '10.0.0.7');
        self::assertCount(1, $this->audit->entries, 'e-mail inexistente não entra no log');

        $forgot->execute('admin@bar.com', '10.0.0.7');
        $requested = $this->audit->last();
        self::assertSame(['auth.password_reset_requested', $admin->id, $admin->id, '10.0.0.7'], [$requested->action, $requested->actorUserId, $requested->entityId, $requested->ip]);
        self::assertSame([], $requested->changes);
        self::assertSame('{}', $requested->changesJson());

        (new ResetPasswordUseCase($this->users, $fakes, new FakeHasher(), $this->clock, $this->audit, $this->audit->auditor()))
            ->execute($fakes->lastToken(), 'nova-senha-123', '10.0.0.8');
        self::assertSame(['auth.password_reset', 'admin@bar.com', '10.0.0.8'], [$this->audit->last()->action, $this->audit->last()->actorEmail, $this->audit->last()->ip]);
        self::assertStringNotContainsString($fakes->lastToken(), json_encode($this->audit->entries, JSON_THROW_ON_ERROR));
    }

    public function test_failed_write_records_nothing(): void
    {
        try {
            (new CreateProductUseCase($this->products, $this->audit, $this->audit->auditor()))
                ->execute(InMemoryAudit::actor(), new ProductData('', 1400, 'bebidas'));
            self::fail('Nome vazio');
        } catch (ValidationError) {
            self::assertSame([], $this->audit->entries);
        }

        $id = $this->createProduct();
        $this->createPromotion($id);
        try {
            $this->createPromotion($id, '19:00', '21:00');
            self::fail('Sobreposição');
        } catch (OverlappingPromotion) {
            self::assertSame(['product.created', 'promotion.created'], $this->audit->actions());
        }

        $broken = $this->createStub(ProductRepository::class);
        $broken->method('nextId')->willReturn(99);
        $broken->method('save')->willThrowException(new \RuntimeException('banco caiu'));
        try {
            (new CreateProductUseCase($broken, $this->audit, $this->audit->auditor()))
                ->execute(InMemoryAudit::actor(), new ProductData('Água', 500, 'bebidas'));
            self::fail('Escrita falhou');
        } catch (\RuntimeException) {
            self::assertCount(2, $this->audit->entries, 'escrita que falha não grava log');
        }
    }

    public function test_diff_and_empty_changes_as_json_object(): void
    {
        self::assertSame(
            ['before' => ['b' => 2, 'c' => null], 'after' => ['b' => 3, 'c' => 4]],
            Auditor::diff(['a' => 1, 'b' => 2], ['a' => 1, 'b' => 3, 'c' => 4]),
        );
        $entry = AuditEntry::by(InMemoryAudit::actor(), 'product.updated', 'product', 1, Auditor::diff(['a' => 1], ['a' => 1]), $this->clock->now());
        self::assertSame('{"before":{},"after":{}}', $entry->changesJson());
    }

    public function test_list_logs_of_user_in_restaurant_timezone(): void
    {
        $create = new CreateUserUseCase($this->users, new FakeHasher(), $this->audit, $this->audit->auditor());
        $admin = $create->bootstrap(1, 'Admin', 'admin@bar.com', 'senha-forte');
        $bia = $create->execute(InMemoryAudit::actor($admin->id), 'Bia', 'bia@bar.com', 'senha-forte');
        $this->clock->set('2026-11-26T12:00:00Z');
        $this->createProduct(); // ator 1 = admin

        $page = $this->listLogs()->execute(1, $admin->id, '2026-11-25', null);
        self::assertSame(3, $page->total, 'criou a si mesmo (bootstrap), criou a Bia e criou um produto');
        $data = $page->toArray();
        self::assertSame(['id' => $admin->id, 'email' => 'admin@bar.com'], $data['user']);
        self::assertSame(['page' => 1, 'perPage' => 50, 'total' => 3], $data['meta']);
        self::assertSame('product.created', $data['data'][0]['action'] ?? null, 'mais recente primeiro');
        self::assertSame('2026-11-26T09:00:00-03:00', $data['data'][0]['createdAt'] ?? null);
        self::assertSame('2026-11-25T18:31:04-03:00', $data['data'][2]['createdAt'] ?? null);

        self::assertSame(2, $this->listLogs()->execute(1, $admin->id, '2026-11-25', '2026-11-25')->total);
        self::assertCount(1, $this->listLogs()->execute(1, $admin->id, '2026-11-25', '2026-11-25', 2, 1)->items, 'página 2 de 1 por página');
        self::assertSame(0, $this->listLogs()->execute(1, $bia->id, '2026-11-25', null)->total, 'Bia não fez nada');

        (new DeleteUserUseCase($this->users, $this->audit, $this->audit->auditor()))->execute(InMemoryAudit::actor($bia->id), $admin->id);
        $deleted = $this->listLogs()->execute(1, $admin->id, '2026-11-25', null);
        self::assertSame('admin@bar.com', $deleted->userEmail, 'usuário excluído ainda tem log');
    }

    public function test_list_logs_not_found_and_validation(): void
    {
        $admin = (new CreateUserUseCase($this->users, new FakeHasher(), $this->audit, $this->audit->auditor()))
            ->bootstrap(1, 'Admin', 'admin@bar.com', 'senha-forte');
        $foreign = (new CreateUserUseCase($this->users, new FakeHasher(), $this->audit, $this->audit->auditor()))
            ->bootstrap(2, 'Outro', 'outro@rest.com', 'senha-forte');

        foreach ([$foreign->id, 999] as $id) {
            try {
                $this->listLogs()->execute(1, $id, '2026-11-25', null);
                self::fail('Era 404');
            } catch (UserNotFound) {
                self::addToAssertionCount(1);
            }
        }
        try {
            $this->listLogs()->execute(1, $admin->id, '2026-11-25', null, 1, 101);
            self::fail('perPage grande demais');
        } catch (ValidationError $e) {
            self::assertSame('page', $e->field());
        }

        $this->expectException(RestaurantNotFound::class);
        $this->listLogs()->execute(2, $foreign->id, '2026-11-25', null);
    }
}
