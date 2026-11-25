<?php

declare(strict_types=1);

namespace Goomer\Product\Domain;

interface ProductRepository
{
    public function nextId(): int;

    public function save(Product $product): void;

    /** Só devolve o produto se ele for do restaurante informado. */
    public function find(int $restaurantId, int $id): ?Product;

    /**
     * @return array{items: list<Product>, total: int}
     */
    public function search(int $restaurantId, ?Category $category, ?bool $visible, int $page, int $perPage): array;

    public function lastPosition(int $restaurantId): int;

    /** @return list<int> ids de todos os produtos do restaurante */
    public function idsOf(int $restaurantId): array;

    /**
     * @param array<int, int> $positions id do produto => nova posição
     */
    public function savePositions(int $restaurantId, array $positions): void;

    public function delete(int $restaurantId, int $id): void;
}
