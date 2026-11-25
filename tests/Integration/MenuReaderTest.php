<?php

declare(strict_types=1);

namespace Goomer\Tests\Integration;

use Goomer\Menu\Infrastructure\PdoMenuReader;
use Goomer\Menu\Infrastructure\PdoRestaurantRepository;
use Goomer\Product\Application\CreateProductUseCase;
use Goomer\Product\Application\ProductData;
use Goomer\Product\Application\ReorderProductsUseCase;
use Goomer\Product\Infrastructure\PdoProductRepository;
use Goomer\Promotion\Application\CreatePromotionUseCase;
use Goomer\Promotion\Application\PromotionData;
use Goomer\Promotion\Infrastructure\PdoPromotionRepository;
use Goomer\Shared\Domain\Timezone;
use Goomer\Tests\Support\TestDatabase;
use PHPUnit\Framework\TestCase;

final class MenuReaderTest extends TestCase
{
    private PdoMenuReader $reader;

    protected function setUp(): void
    {
        $db = TestDatabase::reset();
        $this->reader = new PdoMenuReader($db);
        $products = new PdoProductRepository($db);
        $create = new CreateProductUseCase($products);

        $chopp = $create->execute(1, new ProductData('Chopp', 1400, 'bebidas'))->id;
        $agua = $create->execute(1, new ProductData('Água', 500, 'bebidas'))->id;
        $create->execute(1, new ProductData('Pudim', 1800, 'sobremesas'));
        $create->execute(1, new ProductData('Bolinho', 3200, 'entradas'));
        $create->execute(1, new ProductData('Escondido', 1000, 'entradas', false));
        $create->execute(1, new ProductData('Feijoada', 6500, 'pratos_principais', true, [
            ['weekday' => 6, 'start' => '11:00', 'end' => '16:00'],
        ]));
        $create->execute(2, new ProductData('De outro restaurante', 1000, 'bebidas'));
        (new ReorderProductsUseCase($products))->execute(1, [$agua, $chopp]);

        (new CreatePromotionUseCase($products, new PdoPromotionRepository($db)))->execute(1, $chopp, new PromotionData(
            'Happy hour',
            700,
            [['weekday' => 3, 'start' => '18:00', 'end' => '20:00']],
        ));
    }

    /** @return list<string> */
    private function namesAt(string $instant): array
    {
        $moment = Timezone::fromString('America/Sao_Paulo')->localize(new \DateTimeImmutable($instant));

        return array_map(static fn($item) => $item->name, $this->reader->itemsAt(1, $moment));
    }

    public function test_menu_query_filters_orders_and_applies_promotion(): void
    {
        $moment = Timezone::fromString('America/Sao_Paulo')->localize(new \DateTimeImmutable('2026-12-02T21:30:00Z'));
        $items = $this->reader->itemsAt(1, $moment);

        self::assertSame(['Bolinho', 'Pudim', 'Água', 'Chopp'], array_map(static fn($i) => $i->name, $items));
        $chopp = $items[3];
        self::assertSame(700, $chopp->promotionPrice);
        self::assertSame('Happy hour', $chopp->promotionDescription);
        self::assertSame('20:00', $chopp->promotionEndsAt);
        self::assertNull($items[2]->promotionPrice);
    }

    public function test_promotion_window_is_half_open(): void
    {
        $moment = Timezone::fromString('America/Sao_Paulo')->localize(new \DateTimeImmutable('2026-12-02T23:00:00Z'));
        $chopp = array_values(array_filter($this->reader->itemsAt(1, $moment), static fn($i) => $i->name === 'Chopp'))[0];

        self::assertNull($chopp->promotionPrice);
    }

    public function test_availability_window(): void
    {
        self::assertNotContains('Feijoada', $this->namesAt('2026-12-02T21:30:00Z'));
        self::assertContains('Feijoada', $this->namesAt('2026-12-05T15:00:00Z'));
        self::assertNotContains('Feijoada', $this->namesAt('2026-12-05T19:00:00Z'), 'sábado 16:00 já fechou');
    }

    public function test_restaurant_repository(): void
    {
        $restaurants = new PdoRestaurantRepository(TestDatabase::db());
        self::assertSame('Asia/Tokyo', $restaurants->find(2)?->timezone()->name());
        self::assertNull($restaurants->find(99));
    }
}
