<?php

declare(strict_types=1);

namespace Goomer\Promotion\Application;

use Goomer\Product\Domain\ProductRepository;
use Goomer\Promotion\Domain\PromotionNotFound;
use Goomer\Promotion\Domain\PromotionRepository;

final readonly class UpdatePromotionUseCase
{
    public function __construct(
        private ProductRepository $products,
        private PromotionRepository $promotions,
    ) {}

    public function execute(int $restaurantId, int $id, PromotionData $data): PromotionOutput
    {
        $promotion = $this->promotions->find($restaurantId, $id) ?? throw PromotionNotFound::withId($id);
        $product = $this->products->find($restaurantId, $promotion->productId()) ?? throw PromotionNotFound::withId($id);

        $promotion->update($product->price(), $data->description, $data->price(), $data->timeWindows());
        $promotion->assertNoOverlapWith($this->promotions->ofProduct($product->id()));
        $this->promotions->save($promotion);

        return PromotionOutput::from($promotion);
    }
}
