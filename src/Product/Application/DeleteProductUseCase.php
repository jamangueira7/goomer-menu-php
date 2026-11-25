<?php

declare(strict_types=1);

namespace Goomer\Product\Application;

use Goomer\Product\Domain\ProductNotFound;
use Goomer\Product\Domain\ProductRepository;

/**
 * Exclui o produto; as promoções dele saem junto (ON DELETE CASCADE no banco).
 */
final readonly class DeleteProductUseCase
{
    public function __construct(private ProductRepository $products) {}

    public function execute(int $restaurantId, int $id): void
    {
        if ($this->products->find($restaurantId, $id) === null) {
            throw ProductNotFound::withId($id);
        }
        $this->products->delete($restaurantId, $id);
    }
}
