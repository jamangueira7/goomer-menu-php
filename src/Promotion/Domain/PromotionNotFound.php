<?php

declare(strict_types=1);

namespace Goomer\Promotion\Domain;

use Goomer\Shared\Domain\Exception\NotFound;

final class PromotionNotFound extends NotFound
{
    public static function withId(int $id): self
    {
        return new self(sprintf('Promoção %d não encontrada.', $id));
    }
}
