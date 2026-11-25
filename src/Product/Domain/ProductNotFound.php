<?php

declare(strict_types=1);

namespace Goomer\Product\Domain;

use Goomer\Shared\Domain\Exception\NotFound;

final class ProductNotFound extends NotFound
{
    public static function withId(int $id): self
    {
        return new self(sprintf('Produto %d não encontrado.', $id));
    }
}
