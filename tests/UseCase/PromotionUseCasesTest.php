<?php

declare(strict_types=1);

namespace Goomer\Tests\UseCase;

use Goomer\Product\Application\CreateProductUseCase;
use Goomer\Product\Application\DeleteProductUseCase;
use Goomer\Product\Application\ProductData;
use Goomer\Product\Domain\ProductNotFound;
use Goomer\Promotion\Application\CreatePromotionUseCase;
use Goomer\Promotion\Application\DeletePromotionUseCase;
use Goomer\Promotion\Application\GetPromotionUseCase;
use Goomer\Promotion\Application\ListPromotionsUseCase;
use Goomer\Promotion\Application\PromotionData;
use Goomer\Promotion\Application\UpdatePromotionUseCase;
use Goomer\Promotion\Domain\OverlappingPromotion;
use Goomer\Promotion\Domain\PromotionNotFound;
use Goomer\Shared\Domain\Exception\ValidationError;
use Goomer\Tests\Support\InMemoryProductRepository;
use Goomer\Tests\Support\InMemoryPromotionRepository;
use PHPUnit\Framework\TestCase;

final class PromotionUseCasesTest extends TestCase
{
    private InMemoryProductRepository $products;
    private InMemoryPromotionRepository $promotions;
    private int $chopp;

    protected function setUp(): void
    {
        $this->products = new InMemoryProductRepository();
        $this->promotions = new InMemoryPromotionRepository($this->products);
        $this->chopp = (new CreateProductUseCase($this->products))->execute(1, new ProductData('Chopp', 1400, 'bebidas'))->id;
    }

    private function create(string $start, string $end, int $price = 700, int $restaurant = 1): int
    {
        return (new CreatePromotionUseCase($this->products, $this->promotions))->execute(
            $restaurant,
            $this->chopp,
            new PromotionData('Happy hour', $price, [['weekday' => 3, 'start' => $start, 'end' => $end]]),
        )->id;
    }

    public function test_creates_and_reads_promotion(): void
    {
        $id = $this->create('18:00', '20:00');

        $output = (new GetPromotionUseCase($this->promotions))->execute(1, $id);
        self::assertSame($this->chopp, $output->productId);
        self::assertSame(700, $output->price);
        self::assertSame([['weekday' => 3, 'start' => '18:00', 'end' => '20:00']], $output->windows);
        self::assertSame('Happy hour', $output->toArray()['description']);
    }

    public function test_overlapping_window_of_same_product_is_conflict(): void
    {
        $this->create('18:00', '20:00');
        $this->create('20:00', '21:00');

        $this->expectException(OverlappingPromotion::class);
        $this->create('19:45', '20:15');
    }

    public function test_price_not_lower_than_regular_is_rejected(): void
    {
        $this->expectException(ValidationError::class);
        $this->create('18:00', '20:00', 1400);
    }

    public function test_product_of_other_restaurant_is_not_found(): void
    {
        $this->expectException(ProductNotFound::class);
        $this->create('18:00', '20:00', 700, 2);
    }

    public function test_update_can_keep_own_windows_but_not_collide_with_others(): void
    {
        $first = $this->create('18:00', '20:00');
        $second = $this->create('20:00', '22:00');
        $update = new UpdatePromotionUseCase($this->products, $this->promotions);

        $same = $update->execute(1, $first, new PromotionData('Novo nome', 600, [['weekday' => 3, 'start' => '18:00', 'end' => '20:00']]));
        self::assertSame('Novo nome', $same->description);

        try {
            $update->execute(1, $second, new PromotionData('Choque', 600, [['weekday' => 3, 'start' => '19:00', 'end' => '21:00']]));
            self::fail('Era para dar conflito');
        } catch (OverlappingPromotion) {
            self::addToAssertionCount(1);
        }

        $this->expectException(PromotionNotFound::class);
        $update->execute(2, $first, new PromotionData('Outro restaurante', 600, [['weekday' => 1, 'start' => '18:00', 'end' => '20:00']]));
    }

    public function test_list_filter_and_delete(): void
    {
        $id = $this->create('18:00', '20:00');
        $list = new ListPromotionsUseCase($this->promotions);

        self::assertCount(1, $list->execute(1, null));
        self::assertCount(1, $list->execute(1, $this->chopp));
        self::assertCount(0, $list->execute(1, 999));
        self::assertCount(0, $list->execute(2, null));

        (new DeletePromotionUseCase($this->promotions))->execute(1, $id);
        self::assertCount(0, $list->execute(1, null));

        $this->expectException(PromotionNotFound::class);
        (new DeletePromotionUseCase($this->promotions))->execute(1, $id);
    }

    public function test_deleting_product_removes_its_promotions(): void
    {
        $this->create('18:00', '20:00');
        (new DeleteProductUseCase($this->products))->execute(1, $this->chopp);

        self::assertCount(0, (new ListPromotionsUseCase($this->promotions))->execute(1, null));
    }
}
