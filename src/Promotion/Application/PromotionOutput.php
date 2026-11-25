<?php

declare(strict_types=1);

namespace Goomer\Promotion\Application;

use Goomer\Promotion\Domain\Promotion;
use Goomer\Shared\Domain\TimeWindow;

final readonly class PromotionOutput
{
    /**
     * @param list<array{weekday: int, start: string, end: string}> $windows
     */
    public function __construct(
        public int $id,
        public int $productId,
        public string $description,
        public int $price,
        public array $windows,
    ) {}

    public static function from(Promotion $promotion): self
    {
        return new self(
            $promotion->id(),
            $promotion->productId(),
            $promotion->description(),
            $promotion->price()->cents(),
            array_map(static fn(TimeWindow $w): array => [
                'weekday' => $w->weekday()->value,
                'start' => $w->start()->format(),
                'end' => $w->end()->format(),
            ], $promotion->windows()),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'productId' => $this->productId,
            'description' => $this->description,
            'price' => $this->price,
            'windows' => $this->windows,
        ];
    }
}
