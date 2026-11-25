<?php

declare(strict_types=1);

namespace Goomer\User\Domain;

use Goomer\Shared\Domain\Exception\ValidationError;

final class User
{
    private function __construct(
        private readonly int $id,
        private readonly int $restaurantId,
        private readonly string $name,
        private readonly Email $email,
        private string $passwordHash,
    ) {}

    public static function register(int $id, int $restaurantId, string $name, Email $email, string $passwordHash): self
    {
        $name = trim($name);
        if ($name === '' || mb_strlen($name) > 120) {
            throw new ValidationError('O nome é obrigatório e tem no máximo 120 caracteres.', 'name');
        }

        return new self($id, $restaurantId, $name, $email, $passwordHash);
    }

    public static function restore(int $id, int $restaurantId, string $name, Email $email, string $passwordHash): self
    {
        return new self($id, $restaurantId, $name, $email, $passwordHash);
    }

    public function changePassword(string $passwordHash): void
    {
        $this->passwordHash = $passwordHash;
    }

    public function id(): int
    {
        return $this->id;
    }

    public function restaurantId(): int
    {
        return $this->restaurantId;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function email(): Email
    {
        return $this->email;
    }

    public function passwordHash(): string
    {
        return $this->passwordHash;
    }
}
