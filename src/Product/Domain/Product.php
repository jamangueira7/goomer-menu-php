<?php

declare(strict_types=1);

namespace Goomer\Product\Domain;

use Goomer\Shared\Domain\Exception\ValidationError;
use Goomer\Shared\Domain\LocalMoment;
use Goomer\Shared\Domain\Money;
use Goomer\Shared\Domain\TimeWindow;

/**
 * Produto do cardápio. Sem janelas de disponibilidade, está sempre disponível.
 */
final class Product
{
    public const int MAX_NAME_LENGTH = 120;

    /**
     * @param list<TimeWindow> $availability
     */
    private function __construct(
        private readonly int $id,
        private readonly int $restaurantId,
        private string $name,
        private Money $price,
        private Category $category,
        private bool $visible,
        private int $position,
        private array $availability,
    ) {}

    /**
     * @param list<TimeWindow> $availability
     */
    public static function create(
        int $id,
        int $restaurantId,
        string $name,
        Money $price,
        Category $category,
        bool $visible,
        int $position,
        array $availability = [],
    ): self {
        $product = new self($id, $restaurantId, '', $price, $category, $visible, $position, []);
        $product->update($name, $price, $category, $visible, $availability);

        return $product;
    }

    /**
     * Reconstitui do banco, sem repetir as validações de criação.
     *
     * @param list<TimeWindow> $availability
     */
    public static function restore(
        int $id,
        int $restaurantId,
        string $name,
        Money $price,
        Category $category,
        bool $visible,
        int $position,
        array $availability,
    ): self {
        return new self($id, $restaurantId, $name, $price, $category, $visible, $position, $availability);
    }

    /**
     * @param list<TimeWindow> $availability
     */
    public function update(string $name, Money $price, Category $category, bool $visible, array $availability): void
    {
        $name = trim($name);
        if ($name === '') {
            throw new ValidationError('O nome do produto é obrigatório.', 'name');
        }
        if (mb_strlen($name) > self::MAX_NAME_LENGTH) {
            throw new ValidationError('O nome do produto tem no máximo 120 caracteres.', 'name');
        }
        if ($price->isZero()) {
            throw new ValidationError('O preço do produto precisa ser maior que zero.', 'price');
        }
        if (TimeWindow::anyOverlap($availability)) {
            throw new ValidationError('As janelas de disponibilidade não podem se sobrepor.', 'availability');
        }

        $this->name = $name;
        $this->price = $price;
        $this->category = $category;
        $this->visible = $visible;
        $this->availability = $availability;
    }

    public function show(): void
    {
        $this->visible = true;
    }

    public function hide(): void
    {
        $this->visible = false;
    }

    public function moveTo(int $position): void
    {
        $this->position = $position;
    }

    public function isAvailableAt(LocalMoment $moment): bool
    {
        if ($this->availability === []) {
            return true;
        }
        foreach ($this->availability as $window) {
            if ($window->contains($moment)) {
                return true;
            }
        }

        return false;
    }

    public function belongsTo(int $restaurantId): bool
    {
        return $this->restaurantId === $restaurantId;
    }

    public function id(): int
    {
        return $this->id;
    }

    public function restaurantId(): int
    {
        return $this->restaurantId;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function price(): Money
    {
        return $this->price;
    }

    public function category(): Category
    {
        return $this->category;
    }

    public function isVisible(): bool
    {
        return $this->visible;
    }

    public function position(): int
    {
        return $this->position;
    }

    /** @return list<TimeWindow> */
    public function availability(): array
    {
        return $this->availability;
    }
}
