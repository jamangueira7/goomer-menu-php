<?php

declare(strict_types=1);

namespace Goomer\User\Infrastructure;

use Goomer\User\Application\PasswordHasher;
use Goomer\User\Domain\Password;

final class Argon2idPasswordHasher implements PasswordHasher
{
    public function hash(Password $password): string
    {
        return password_hash($password->plain(), PASSWORD_ARGON2ID);
    }

    public function verify(string $plain, string $hash): bool
    {
        return password_verify($plain, $hash);
    }
}
