<?php

declare(strict_types=1);

namespace Goomer\Shared\Domain;

use Goomer\Shared\Domain\Exception\ValidationError;

/**
 * Dia da semana no padrão do PHP e do Postgres: 0 é domingo, 6 é sábado.
 */
enum Weekday: int
{
    case Sunday = 0;
    case Monday = 1;
    case Tuesday = 2;
    case Wednesday = 3;
    case Thursday = 4;
    case Friday = 5;
    case Saturday = 6;

    public static function fromInt(int $value): self
    {
        return self::tryFrom($value)
            ?? throw new ValidationError('O dia da semana vai de 0 (domingo) a 6 (sábado).', 'weekday');
    }

    public static function of(\DateTimeImmutable $moment): self
    {
        return self::from((int) $moment->format('w'));
    }
}
