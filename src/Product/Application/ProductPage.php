<?php

declare(strict_types=1);

namespace Goomer\Product\Application;

/**
 * Página da listagem de produtos.
 */
final readonly class ProductPage
{
    /**
     * @param list<ProductOutput> $items
     */
    public function __construct(
        public array $items,
        public int $page,
        public int $perPage,
        public int $total,
    ) {}
}
