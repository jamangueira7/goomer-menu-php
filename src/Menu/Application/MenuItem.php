<?php

declare(strict_types=1);

namespace Goomer\Menu\Application;

/**
 * Linha do cardápio já filtrada: produto visível e disponível, com a promoção ativa (se houver).
 */
final readonly class MenuItem
{
    public function __construct(
        public int $id,
        public string $name,
        public string $category,
        public int $price,
        public ?string $promotionDescription = null,
        public ?int $promotionPrice = null,
        public ?string $promotionEndsAt = null,
    ) {}
}
