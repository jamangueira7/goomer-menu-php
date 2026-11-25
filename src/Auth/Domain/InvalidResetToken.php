<?php

declare(strict_types=1);

namespace Goomer\Auth\Domain;

use Goomer\Shared\Domain\Exception\ValidationError;

final class InvalidResetToken extends ValidationError
{
    public function __construct()
    {
        parent::__construct('Token de redefinição inválido, já usado ou vencido.', 'token');
    }
}
