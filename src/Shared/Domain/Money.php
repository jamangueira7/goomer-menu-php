<?php

declare(strict_types=1);

namespace Goomer\Shared\Domain;

use Goomer\Shared\Domain\Exception\ValidationError;

/**
 * Valor monetário em centavos (inteiro), nunca negativo e nunca ponto flutuante.
 */
final readonly class Money
{
    private function __construct(private int $cents) {}

    public static function ofCents(int $cents): self
    {
        if ($cents < 0) {
            throw new ValidationError('O valor em centavos não pode ser negativo.', 'price');
        }

        return new self($cents);
    }

    public function cents(): int
    {
        return $this->cents;
    }

    public function isZero(): bool
    {
        return $this->cents === 0;
    }

    public function isLessThan(self $other): bool
    {
        return $this->cents < $other->cents;
    }

    public function equals(self $other): bool
    {
        return $this->cents === $other->cents;
    }
}
