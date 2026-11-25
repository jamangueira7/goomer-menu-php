<?php

declare(strict_types=1);

namespace Goomer\User\Domain;

use Goomer\Shared\Domain\Exception\NotFound;

final class UserNotFound extends NotFound
{
    public static function withId(int $id): self
    {
        return new self(sprintf('Usuário %d não encontrado.', $id));
    }
}
