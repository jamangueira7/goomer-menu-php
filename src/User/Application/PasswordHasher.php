<?php

declare(strict_types=1);

namespace Goomer\User\Application;

use Goomer\User\Domain\Password;

interface PasswordHasher
{
    public function hash(Password $password): string;

    public function verify(string $plain, string $hash): bool;
}
