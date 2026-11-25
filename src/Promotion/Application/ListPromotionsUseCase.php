<?php

declare(strict_types=1);

namespace Goomer\Promotion\Application;

use Goomer\Promotion\Domain\PromotionRepository;

final readonly class ListPromotionsUseCase
{
    public function __construct(private PromotionRepository $promotions) {}

    /**
     * @return list<PromotionOutput>
     */
    public function execute(int $restaurantId, ?int $productId): array
    {
        return array_map(PromotionOutput::from(...), $this->promotions->ofRestaurant($restaurantId, $productId));
    }
}
