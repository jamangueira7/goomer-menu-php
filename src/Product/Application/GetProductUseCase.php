<?php

declare(strict_types=1);

namespace Goomer\Product\Application;

use Goomer\Product\Domain\ProductNotFound;
use Goomer\Product\Domain\ProductRepository;

final readonly class GetProductUseCase
{
    public function __construct(private ProductRepository $products) {}

    public function execute(int $restaurantId, int $id): ProductOutput
    {
        return ProductOutput::from($this->products->find($restaurantId, $id) ?? throw ProductNotFound::withId($id));
    }
}
