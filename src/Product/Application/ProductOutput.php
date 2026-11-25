<?php

declare(strict_types=1);

namespace Goomer\Product\Application;

use Goomer\Product\Domain\Product;
use Goomer\Shared\Domain\TimeWindow;

final readonly class ProductOutput
{
    /**
     * @param list<array{weekday: int, start: string, end: string}> $availability
     */
    public function __construct(
        public int $id,
        public string $name,
        public int $price,
        public string $category,
        public bool $visible,
        public int $position,
        public array $availability,
    ) {}

    public static function from(Product $product): self
    {
        return new self(
            $product->id(),
            $product->name(),
            $product->price()->cents(),
            $product->category()->value,
            $product->isVisible(),
            $product->position(),
            array_map(static fn(TimeWindow $w): array => [
                'weekday' => $w->weekday()->value,
                'start' => $w->start()->format(),
                'end' => $w->end()->format(),
            ], $product->availability()),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'price' => $this->price,
            'category' => $this->category,
            'visible' => $this->visible,
            'position' => $this->position,
            'availability' => $this->availability,
        ];
    }
}
