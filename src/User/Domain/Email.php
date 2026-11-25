<?php

declare(strict_types=1);

namespace Goomer\User\Domain;

use Goomer\Shared\Domain\Exception\ValidationError;

/**
 * E-mail normalizado em minúsculas, sem espaços nas pontas.
 */
final readonly class Email
{
    private function __construct(private string $value) {}

    public static function fromString(string $value): self
    {
        $value = mb_strtolower(trim($value));
        if (filter_var($value, FILTER_VALIDATE_EMAIL) === false || strlen($value) > 254) {
            throw new ValidationError('E-mail inválido.', 'email');
        }

        return new self($value);
    }

    public function value(): string
    {
        return $this->value;
    }
}
