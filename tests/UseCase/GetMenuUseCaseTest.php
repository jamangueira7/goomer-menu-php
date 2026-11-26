<?php

declare(strict_types=1);

namespace Goomer\Tests\UseCase;

use Goomer\Menu\Application\GetMenuUseCase;
use Goomer\Menu\Domain\Restaurant;
use Goomer\Menu\Domain\RestaurantNotFound;
use Goomer\Product\Application\CreateProductUseCase;
use Goomer\Product\Application\ProductData;
use Goomer\Promotion\Application\CreatePromotionUseCase;
use Goomer\Promotion\Application\PromotionData;
use Goomer\Shared\Domain\Timezone;
use Goomer\Tests\Support\FixedClock;
use Goomer\Tests\Support\InMemoryAudit;
use Goomer\Tests\Support\InMemoryMenu;
use Goomer\Tests\Support\InMemoryProductRepository;
use Goomer\Tests\Support\InMemoryPromotionRepository;
use PHPUnit\Framework\TestCase;

final class GetMenuUseCaseTest extends TestCase
{
    private InMemoryMenu $menu;
    private FixedClock $clock;

    protected function setUp(): void
    {
        $products = new InMemoryProductRepository();
        $promotions = new InMemoryPromotionRepository($products);
        $this->menu = new InMemoryMenu($products, $promotions);
        $this->menu->addRestaurant(new Restaurant(1, 'Bar do João', Timezone::fromString('America/Sao_Paulo')));
        $this->menu->addRestaurant(new Restaurant(2, 'Izakaya', Timezone::fromString('Asia/Tokyo')));
        $this->clock = new FixedClock('2026-12-02T21:30:00Z'); // quarta, 18:30 em São Paulo

        $audit = new InMemoryAudit();
        $create = new CreateProductUseCase($products, $audit, $audit->auditor());
        $chopp = $create->execute(InMemoryAudit::actor(1, 1), new ProductData('Chopp 300 ml', 1400, 'bebidas'))->id;
        $create->execute(InMemoryAudit::actor(1, 1), new ProductData('Pudim', 1800, 'sobremesas'));
        $create->execute(InMemoryAudit::actor(1, 1), new ProductData('Bolinho', 3200, 'entradas'));
        $create->execute(InMemoryAudit::actor(1, 1), new ProductData('Escondido', 1000, 'entradas', false));
        $create->execute(InMemoryAudit::actor(1, 1), new ProductData('Feijoada', 6500, 'pratos_principais', true, [
            ['weekday' => 6, 'start' => '11:00', 'end' => '16:00'],
        ]));
        $create->execute(InMemoryAudit::actor(1, 2), new ProductData('Lámen', 4500, 'pratos_principais', true, [
            ['weekday' => 4, 'start' => '06:00', 'end' => '07:00'],
        ]));

        (new CreatePromotionUseCase($products, $promotions, $audit, $audit->auditor()))->execute(InMemoryAudit::actor(), $chopp, new PromotionData(
            'Happy hour: chopp pela metade do preço',
            700,
            [['weekday' => 3, 'start' => '18:00', 'end' => '20:00']],
        ));
    }

    /**
     * @return array{
     *     restaurant: array{id: int, name: string, timezone: string},
     *     generatedAt: string,
     *     categories: list<array{key: string, name: string, products: list<array<string, mixed>>}>
     * }
     */
    private function menuAt(int $restaurant): array
    {
        return (new GetMenuUseCase($this->menu, $this->menu, $this->clock))->execute($restaurant);
    }

    public function test_wednesday_happy_hour_menu(): void
    {
        $menu = $this->menuAt(1);

        self::assertSame(['id' => 1, 'name' => 'Bar do João', 'timezone' => 'America/Sao_Paulo'], $menu['restaurant']);
        self::assertSame('2026-12-02T18:30:00-03:00', $menu['generatedAt']);
        self::assertSame(['Entradas', 'Sobremesas', 'Bebidas'], array_column($menu['categories'], 'name'));
        self::assertSame(['Bolinho'], array_column($menu['categories'][0]['products'], 'name'), 'invisível não aparece');

        $chopp = $menu['categories'][2]['products'][0];
        self::assertSame(1400, $chopp['price']);
        self::assertSame(
            ['description' => 'Happy hour: chopp pela metade do preço', 'price' => 700, 'endsAt' => '20:00'],
            $chopp['promotion'],
        );
    }

    public function test_promotion_ends_at_window_end(): void
    {
        $this->clock->set('2026-12-02T23:00:00Z'); // 20:00 local: janela já fechou
        $chopp = $this->menuAt(1)['categories'][2]['products'][0];

        self::assertNull($chopp['promotion']);
    }

    public function test_availability_window_on_saturday_lunch(): void
    {
        $this->clock->set('2026-12-05T15:00:00Z'); // sábado, 12:00 local
        $menu = $this->menuAt(1);

        self::assertContains('Pratos principais', array_column($menu['categories'], 'name'));
    }

    public function test_uses_restaurant_timezone(): void
    {
        // Mesmo instante: quarta 18:30 em São Paulo e quinta 06:30 em Tóquio.
        $menu = $this->menuAt(2);

        self::assertSame('2026-12-03T06:30:00+09:00', $menu['generatedAt']);
        self::assertSame('Lámen', $menu['categories'][0]['products'][0]['name']);
    }

    public function test_unknown_restaurant(): void
    {
        $this->expectException(RestaurantNotFound::class);
        $this->menuAt(99);
    }
}
