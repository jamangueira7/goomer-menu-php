<?php

declare(strict_types=1);

namespace Goomer\Promotion\Domain;

interface PromotionRepository
{
    public function nextId(): int;

    /**
     * Grava a promoção e as janelas. Lança OverlappingPromotion se o banco recusar a sobreposição.
     */
    public function save(Promotion $promotion): void;

    /** Só devolve a promoção se o produto dela for do restaurante informado. */
    public function find(int $restaurantId, int $id): ?Promotion;

    /** @return list<Promotion> */
    public function ofProduct(int $productId): array;

    /** @return list<Promotion> */
    public function ofRestaurant(int $restaurantId, ?int $productId): array;

    public function delete(int $id): void;
}
