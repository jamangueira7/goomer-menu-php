<?php

declare(strict_types=1);

namespace Goomer\Promotion\Application;

use Goomer\Shared\Domain\Money;
use Goomer\Shared\Domain\TimeWindow;

/**
 * Descrição, preço e janelas de uma promoção (entrada de criação e de atualização).
 */
final readonly class PromotionData
{
    /**
     * @param list<array{weekday: int, start: string, end: string}> $windows
     */
    public function __construct(
        public string $description,
        public int $priceCents,
        public array $windows,
    ) {}

    public function price(): Money
    {
        return Money::ofCents($this->priceCents);
    }

    /** @return list<TimeWindow> */
    public function timeWindows(): array
    {
        return array_map(
            static fn(array $w): TimeWindow => TimeWindow::fromStrings($w['weekday'], $w['start'], $w['end']),
            $this->windows,
        );
    }
}
