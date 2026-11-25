<?php

declare(strict_types=1);

namespace Goomer\User\Domain;

use Goomer\Shared\Domain\Exception\ValidationError;

final class CannotDeleteSelf extends ValidationError
{
    public static function create(): self
    {
        return new self('Você não pode remover a si mesmo.', 'id');
    }
}
