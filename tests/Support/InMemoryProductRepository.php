<?php

declare(strict_types=1);

namespace Goomer\Tests\Support;

use Goomer\Product\Domain\Category;
use Goomer\Product\Domain\Product;
use Goomer\Product\Domain\ProductRepository;

final class InMemoryProductRepository implements ProductRepository
{
    /** @var array<int, Product> */
    public array $items = [];
    private int $sequence = 0;

    public function nextId(): int
    {
        return ++$this->sequence;
    }

    public function save(Product $product): void
    {
        $this->items[$product->id()] = clone $product;
    }

    public function find(int $restaurantId, int $id): ?Product
    {
        $product = $this->items[$id] ?? null;

        return $product !== null && $product->belongsTo($restaurantId) ? clone $product : null;
    }

    public function search(int $restaurantId, ?Category $category, ?bool $visible, int $page, int $perPage): array
    {
        $matches = array_values(array_filter(
            $this->sorted($restaurantId),
            static fn(Product $p): bool => ($category === null || $p->category() === $category)
                && ($visible === null || $p->isVisible() === $visible),
        ));

        return ['items' => array_slice($matches, ($page - 1) * $perPage, $perPage), 'total' => count($matches)];
    }

    public function lastPosition(int $restaurantId): int
    {
        return array_reduce($this->sorted($restaurantId), static fn(int $max, Product $p): int => max($max, $p->position()), 0);
    }

    public function idsOf(int $restaurantId): array
    {
        return array_map(static fn(Product $p): int => $p->id(), $this->sorted($restaurantId));
    }

    public function savePositions(int $restaurantId, array $positions): void
    {
        foreach ($positions as $id => $position) {
            if (isset($this->items[$id]) && $this->items[$id]->belongsTo($restaurantId)) {
                $this->items[$id]->moveTo($position);
            }
        }
    }

    public function delete(int $restaurantId, int $id): void
    {
        if (isset($this->items[$id]) && $this->items[$id]->belongsTo($restaurantId)) {
            unset($this->items[$id]);
        }
    }

    /** @return list<Product> */
    private function sorted(int $restaurantId): array
    {
        $list = array_values(array_filter($this->items, static fn(Product $p): bool => $p->belongsTo($restaurantId)));
        usort($list, static fn(Product $a, Product $b): int => [$a->position(), $a->id()] <=> [$b->position(), $b->id()]);

        return $list;
    }
}
