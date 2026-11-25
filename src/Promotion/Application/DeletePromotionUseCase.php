<?php

declare(strict_types=1);

namespace Goomer\Promotion\Application;

use Goomer\Promotion\Domain\PromotionNotFound;
use Goomer\Promotion\Domain\PromotionRepository;

final readonly class DeletePromotionUseCase
{
    public function __construct(private PromotionRepository $promotions) {}

    public function execute(int $restaurantId, int $id): void
    {
        $promotion = $this->promotions->find($restaurantId, $id) ?? throw PromotionNotFound::withId($id);
        $this->promotions->delete($promotion->id());
    }
}
