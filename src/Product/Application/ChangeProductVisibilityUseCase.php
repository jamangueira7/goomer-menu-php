<?php

declare(strict_types=1);

namespace Goomer\Product\Application;

use Goomer\Product\Domain\ProductNotFound;
use Goomer\Product\Domain\ProductRepository;

final readonly class ChangeProductVisibilityUseCase
{
    public function __construct(private ProductRepository $products) {}

    public function execute(int $restaurantId, int $id, bool $visible): ProductOutput
    {
        $product = $this->products->find($restaurantId, $id) ?? throw ProductNotFound::withId($id);
        $visible ? $product->show() : $product->hide();
        $this->products->save($product);

        return ProductOutput::from($product);
    }
}
