<?php

declare(strict_types=1);

namespace Goomer\Promotion\Application;

use Goomer\Product\Domain\ProductNotFound;
use Goomer\Product\Domain\ProductRepository;
use Goomer\Promotion\Domain\Promotion;
use Goomer\Promotion\Domain\PromotionRepository;

final readonly class CreatePromotionUseCase
{
    public function __construct(
        private ProductRepository $products,
        private PromotionRepository $promotions,
    ) {}

    public function execute(int $restaurantId, int $productId, PromotionData $data): PromotionOutput
    {
        $product = $this->products->find($restaurantId, $productId) ?? throw ProductNotFound::withId($productId);

        $promotion = Promotion::create(
            $this->promotions->nextId(),
            $product->id(),
            $product->price(),
            $data->description,
            $data->price(),
            $data->timeWindows(),
        );
        $promotion->assertNoOverlapWith($this->promotions->ofProduct($product->id()));
        $this->promotions->save($promotion);

        return PromotionOutput::from($promotion);
    }
}
