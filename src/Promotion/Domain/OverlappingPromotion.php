<?php

declare(strict_types=1);

namespace Goomer\Promotion\Domain;

use Goomer\Shared\Domain\Exception\Conflict;
use Goomer\Shared\Domain\TimeWindow;

final class OverlappingPromotion extends Conflict
{
    public static function with(int $otherPromotionId, TimeWindow $window): self
    {
        return new self(sprintf(
            'Janela sobreposta à promoção %d (dia %d, %s–%s).',
            $otherPromotionId,
            $window->weekday()->value,
            $window->start()->format(),
            $window->end()->format(),
        ));
    }
}
