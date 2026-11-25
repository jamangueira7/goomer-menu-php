<?php

declare(strict_types=1);

namespace Goomer\User\Application;

use Goomer\User\Domain\User;

/**
 * Usuário para fora da aplicação: nunca leva o hash da senha.
 */
final readonly class UserOutput
{
    public function __construct(
        public int $id,
        public int $restaurantId,
        public string $name,
        public string $email,
    ) {}

    public static function from(User $user): self
    {
        return new self($user->id(), $user->restaurantId(), $user->name(), $user->email()->value());
    }

    /**
     * @return array<string, int|string>
     */
    public function toArray(): array
    {
        return ['id' => $this->id, 'restaurantId' => $this->restaurantId, 'name' => $this->name, 'email' => $this->email];
    }
}
