<?php

declare(strict_types=1);

namespace Goomer\Product\Application;

use Goomer\Product\Domain\Category;
use Goomer\Shared\Domain\Money;
use Goomer\Shared\Domain\TimeWindow;

/**
 * Entrada de criação e de atualização completa de um produto (dados já no formato primitivo).
 */
final readonly class ProductData
{
    /**
     * @param list<array{weekday: int, start: string, end: string}> $availability
     */
    public function __construct(
        public string $name,
        public int $priceCents,
        public string $category,
        public bool $visible = true,
        public array $availability = [],
    ) {}

    public function price(): Money
    {
        return Money::ofCents($this->priceCents);
    }

    public function categoryEnum(): Category
    {
        return Category::fromString($this->category);
    }

    /** @return list<TimeWindow> */
    public function windows(): array
    {
        return array_map(
            static fn(array $w): TimeWindow => TimeWindow::fromStrings($w['weekday'], $w['start'], $w['end']),
            $this->availability,
        );
    }
}
