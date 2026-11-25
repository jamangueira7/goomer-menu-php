<?php

declare(strict_types=1);

namespace Goomer\Promotion\Domain;

use Goomer\Shared\Domain\Exception\ValidationError;
use Goomer\Shared\Domain\LocalMoment;
use Goomer\Shared\Domain\Money;
use Goomer\Shared\Domain\TimeWindow;

/**
 * Promoção de um produto: preço promocional menor que o normal, válido só nas janelas de dia e horário.
 */
final class Promotion
{
    public const int MAX_DESCRIPTION_LENGTH = 255;

    /**
     * @param list<TimeWindow> $windows
     */
    private function __construct(
        private readonly int $id,
        private readonly int $productId,
        private string $description,
        private Money $price,
        private array $windows,
    ) {}

    /**
     * @param list<TimeWindow> $windows
     */
    public static function create(
        int $id,
        int $productId,
        Money $regularPrice,
        string $description,
        Money $price,
        array $windows,
    ): self {
        $promotion = new self($id, $productId, '', $price, []);
        $promotion->update($regularPrice, $description, $price, $windows);

        return $promotion;
    }

    /**
     * @param list<TimeWindow> $windows
     */
    public static function restore(int $id, int $productId, string $description, Money $price, array $windows): self
    {
        return new self($id, $productId, $description, $price, $windows);
    }

    /**
     * @param list<TimeWindow> $windows
     */
    public function update(Money $regularPrice, string $description, Money $price, array $windows): void
    {
        $description = trim($description);
        if ($description === '') {
            throw new ValidationError('A descrição da promoção é obrigatória.', 'description');
        }
        if (mb_strlen($description) > self::MAX_DESCRIPTION_LENGTH) {
            throw new ValidationError('A descrição tem no máximo 255 caracteres.', 'description');
        }
        if ($price->isZero()) {
            throw new ValidationError('O preço promocional precisa ser maior que zero.', 'price');
        }
        if (!$price->isLessThan($regularPrice)) {
            throw new ValidationError(sprintf(
                'O preço promocional (%d) precisa ser menor que o preço normal (%d).',
                $price->cents(),
                $regularPrice->cents(),
            ), 'price');
        }
        if ($windows === []) {
            throw new ValidationError('A promoção precisa de ao menos uma janela de dia e horário.', 'windows');
        }
        if (TimeWindow::anyOverlap($windows)) {
            throw new OverlappingPromotion('As janelas da própria promoção se sobrepõem.');
        }

        $this->description = $description;
        $this->price = $price;
        $this->windows = $windows;
    }

    /**
     * Garante que nenhuma janela desta promoção cruza com as das outras promoções do mesmo produto.
     *
     * @param list<self> $others
     */
    public function assertNoOverlapWith(array $others): void
    {
        foreach ($others as $other) {
            if ($other->id === $this->id || $other->productId !== $this->productId) {
                continue;
            }
            foreach ($this->windows as $mine) {
                foreach ($other->windows as $theirs) {
                    if ($mine->overlaps($theirs)) {
                        throw OverlappingPromotion::with($other->id, $theirs);
                    }
                }
            }
        }
    }

    public function activeWindowAt(LocalMoment $moment): ?TimeWindow
    {
        foreach ($this->windows as $window) {
            if ($window->contains($moment)) {
                return $window;
            }
        }

        return null;
    }

    public function id(): int
    {
        return $this->id;
    }

    public function productId(): int
    {
        return $this->productId;
    }

    public function description(): string
    {
        return $this->description;
    }

    public function price(): Money
    {
        return $this->price;
    }

    /** @return list<TimeWindow> */
    public function windows(): array
    {
        return $this->windows;
    }
}
