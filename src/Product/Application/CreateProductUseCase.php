<?php

declare(strict_types=1);

namespace Goomer\Product\Application;

use Goomer\Product\Domain\Product;
use Goomer\Product\Domain\ProductRepository;

final readonly class CreateProductUseCase
{
    public function __construct(private ProductRepository $products) {}

    public function execute(int $restaurantId, ProductData $data): ProductOutput
    {
        $product = Product::create(
            $this->products->nextId(),
            $restaurantId,
            $data->name,
            $data->price(),
            $data->categoryEnum(),
            $data->visible,
            $this->products->lastPosition($restaurantId) + 1,
            $data->windows(),
        );
        $this->products->save($product);

        return ProductOutput::from($product);
    }
}
