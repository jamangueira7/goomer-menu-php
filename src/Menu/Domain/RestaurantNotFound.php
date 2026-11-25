<?php

declare(strict_types=1);

namespace Goomer\Menu\Domain;

use Goomer\Shared\Domain\Exception\NotFound;

final class RestaurantNotFound extends NotFound
{
    public static function withId(int $id): self
    {
        return new self(sprintf('Restaurante %d não encontrado.', $id));
    }
}
