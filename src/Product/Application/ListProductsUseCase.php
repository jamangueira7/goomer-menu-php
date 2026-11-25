<?php

declare(strict_types=1);

namespace Goomer\Product\Application;

use Goomer\Product\Domain\Category;
use Goomer\Product\Domain\ProductRepository;
use Goomer\Shared\Domain\Exception\ValidationError;

final readonly class ListProductsUseCase
{
    public const int MAX_PER_PAGE = 100;

    public function __construct(private ProductRepository $products) {}

    public function execute(int $restaurantId, ?string $category, ?bool $visible, int $page = 1, int $perPage = 20): ProductPage
    {
        if ($page < 1 || $perPage < 1 || $perPage > self::MAX_PER_PAGE) {
            throw new ValidationError('page precisa ser >= 1 e perPage entre 1 e 100.', 'page');
        }

        $result = $this->products->search(
            $restaurantId,
            $category === null ? null : Category::fromString($category),
            $visible,
            $page,
            $perPage,
        );

        return new ProductPage(
            array_map(ProductOutput::from(...), $result['items']),
            $page,
            $perPage,
            $result['total'],
        );
    }
}
