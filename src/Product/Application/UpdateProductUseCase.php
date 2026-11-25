<?php

declare(strict_types=1);

namespace Goomer\Product\Application;

use Goomer\Product\Domain\ProductNotFound;
use Goomer\Product\Domain\ProductRepository;

final readonly class UpdateProductUseCase
{
    public function __construct(private ProductRepository $products) {}

    public function execute(int $restaurantId, int $id, ProductData $data): ProductOutput
    {
        $product = $this->products->find($restaurantId, $id) ?? throw ProductNotFound::withId($id);
        $product->update($data->name, $data->price(), $data->categoryEnum(), $data->visible, $data->windows());
        $this->products->save($product);

        return ProductOutput::from($product);
    }
}
