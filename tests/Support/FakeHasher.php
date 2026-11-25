<?php

declare(strict_types=1);

namespace Goomer\Tests\Support;

use Goomer\User\Application\PasswordHasher;
use Goomer\User\Domain\Password;

/** Hasher reversível e rápido, só para testes de caso de uso. */
final class FakeHasher implements PasswordHasher
{
    public function hash(Password $password): string
    {
        return 'hashed:' . $password->plain();
    }

    public function verify(string $plain, string $hash): bool
    {
        return $hash === 'hashed:' . $plain;
    }
}
