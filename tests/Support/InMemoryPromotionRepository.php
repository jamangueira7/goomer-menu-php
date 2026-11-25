<?php

declare(strict_types=1);

namespace Goomer\Tests\Support;

use Goomer\Promotion\Domain\Promotion;
use Goomer\Promotion\Domain\PromotionRepository;

final class InMemoryPromotionRepository implements PromotionRepository
{
    /** @var array<int, Promotion> */
    public array $items = [];
    private int $sequence = 0;

    public function __construct(private readonly InMemoryProductRepository $products) {}

    public function nextId(): int
    {
        return ++$this->sequence;
    }

    public function save(Promotion $promotion): void
    {
        $this->items[$promotion->id()] = clone $promotion;
    }

    public function find(int $restaurantId, int $id): ?Promotion
    {
        $promotion = $this->items[$id] ?? null;
        if ($promotion === null || $this->products->find($restaurantId, $promotion->productId()) === null) {
            return null;
        }

        return clone $promotion;
    }

    public function ofProduct(int $productId): array
    {
        return array_values(array_filter($this->live(), static fn(Promotion $p): bool => $p->productId() === $productId));
    }

    public function ofRestaurant(int $restaurantId, ?int $productId): array
    {
        return array_values(array_filter(
            $this->live(),
            fn(Promotion $p): bool => $this->products->find($restaurantId, $p->productId()) !== null
                && ($productId === null || $p->productId() === $productId),
        ));
    }

    public function delete(int $id): void
    {
        unset($this->items[$id]);
    }

    /**
     * Promoções cujo produto ainda existe (simula o ON DELETE CASCADE).
     *
     * @return list<Promotion>
     */
    private function live(): array
    {
        return array_values(array_filter(
            $this->items,
            fn(Promotion $p): bool => isset($this->products->items[$p->productId()]),
        ));
    }
}
