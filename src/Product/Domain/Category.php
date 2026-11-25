<?php

declare(strict_types=1);

namespace Goomer\Product\Domain;

use Goomer\Shared\Domain\Exception\ValidationError;

/**
 * Categoria do produto. A ordem dos casos é a ordem do cardápio (e do ENUM no Postgres).
 */
enum Category: string
{
    case Starters = 'entradas';
    case MainCourses = 'pratos_principais';
    case Desserts = 'sobremesas';
    case Drinks = 'bebidas';

    public static function fromString(string $value): self
    {
        return self::tryFrom($value) ?? throw new ValidationError(sprintf(
            'Categoria "%s" inválida. Use: %s.',
            $value,
            implode(', ', array_map(static fn(self $c): string => $c->value, self::cases())),
        ), 'category');
    }

    public function label(): string
    {
        return match ($this) {
            self::Starters => 'Entradas',
            self::MainCourses => 'Pratos principais',
            self::Desserts => 'Sobremesas',
            self::Drinks => 'Bebidas',
        };
    }
}
