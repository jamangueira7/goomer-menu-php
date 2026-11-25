<?php

declare(strict_types=1);

namespace Goomer\User\Domain;

use Goomer\Shared\Domain\Exception\Conflict;

final class EmailAlreadyTaken extends Conflict
{
    public static function of(Email $email): self
    {
        return new self(sprintf('Já existe um usuário com o e-mail %s.', $email->value()));
    }
}
