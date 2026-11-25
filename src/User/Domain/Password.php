<?php

declare(strict_types=1);

namespace Goomer\User\Domain;

use Goomer\Shared\Domain\Exception\ValidationError;

/**
 * Senha em texto puro, só em memória: tem no mínimo 8 caracteres e só é guardada como hash.
 */
final readonly class Password
{
    public const int MIN_LENGTH = 8;
    public const int MAX_LENGTH = 128;

    private function __construct(private string $plain) {}

    public static function fromString(string $plain): self
    {
        $length = mb_strlen($plain);
        if ($length < self::MIN_LENGTH) {
            throw new ValidationError('A senha precisa ter no mínimo 8 caracteres.', 'password');
        }
        if ($length > self::MAX_LENGTH) {
            throw new ValidationError('A senha tem no máximo 128 caracteres.', 'password');
        }

        return new self($plain);
    }

    public function plain(): string
    {
        return $this->plain;
    }

    /** Evita que a senha apareça em var_dump e em logs de exceção. */
    public function __debugInfo(): array
    {
        return ['plain' => '***'];
    }
}
