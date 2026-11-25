<?php

declare(strict_types=1);

namespace Goomer\Tests\Unit\Promotion;

use Goomer\Promotion\Domain\OverlappingPromotion;
use Goomer\Promotion\Domain\Promotion;
use Goomer\Shared\Domain\Exception\ValidationError;
use Goomer\Shared\Domain\Money;
use Goomer\Shared\Domain\TimeWindow;
use Goomer\Shared\Domain\Timezone;
use PHPUnit\Framework\TestCase;

final class PromotionTest extends TestCase
{
    private function happyHour(int $id = 1, string $start = '18:00', string $end = '20:00'): Promotion
    {
        return Promotion::create(
            $id,
            7,
            Money::ofCents(1400),
            'Happy hour',
            Money::ofCents(700),
            [TimeWindow::fromStrings(3, $start, $end)],
        );
    }

    public function test_promotional_price_must_be_lower_than_regular(): void
    {
        self::assertSame(700, $this->happyHour()->price()->cents());

        $this->expectException(ValidationError::class);
        $this->expectExceptionMessageMatches('/menor que o preço normal/');
        Promotion::create(1, 7, Money::ofCents(1400), 'Igual', Money::ofCents(1400), [TimeWindow::fromStrings(3, '18:00', '20:00')]);
    }

    public function test_requires_description_positive_price_and_windows(): void
    {
        $errors = 0;
        foreach ([
            static fn() => Promotion::create(1, 7, Money::ofCents(1400), ' ', Money::ofCents(700), [TimeWindow::fromStrings(3, '18:00', '20:00')]),
            static fn() => Promotion::create(1, 7, Money::ofCents(1400), str_repeat('x', 256), Money::ofCents(700), [TimeWindow::fromStrings(3, '18:00', '20:00')]),
            static fn() => Promotion::create(1, 7, Money::ofCents(1400), 'Grátis', Money::ofCents(0), [TimeWindow::fromStrings(3, '18:00', '20:00')]),
            static fn() => Promotion::create(1, 7, Money::ofCents(1400), 'Sem janela', Money::ofCents(700), []),
        ] as $attempt) {
            try {
                $attempt();
            } catch (ValidationError) {
                ++$errors;
            }
        }

        self::assertSame(4, $errors);
    }

    public function test_own_windows_cannot_overlap(): void
    {
        $this->expectException(OverlappingPromotion::class);
        Promotion::create(1, 7, Money::ofCents(1400), 'Dupla', Money::ofCents(700), [
            TimeWindow::fromStrings(3, '18:00', '20:00'),
            TimeWindow::fromStrings(3, '19:00', '21:00'),
        ]);
    }

    public function test_conflicts_with_other_promotion_of_same_product(): void
    {
        $existing = $this->happyHour(1);
        $adjacent = $this->happyHour(2, '20:00', '22:00');
        $adjacent->assertNoOverlapWith([$existing, $adjacent]);

        $this->expectException(OverlappingPromotion::class);
        $this->expectExceptionMessageMatches('/promoção 1/');
        $this->happyHour(3, '19:30', '21:00')->assertNoOverlapWith([$existing]);
    }

    public function test_ignores_other_products(): void
    {
        $other = Promotion::restore(9, 8, 'Outro produto', Money::ofCents(500), [TimeWindow::fromStrings(3, '18:00', '20:00')]);
        $this->happyHour()->assertNoOverlapWith([$other]);
        self::assertSame(8, $other->productId());
    }

    public function test_active_window_at_local_moment(): void
    {
        $tz = Timezone::fromString('America/Sao_Paulo');
        $promotion = $this->happyHour();

        $active = $promotion->activeWindowAt($tz->localize(new \DateTimeImmutable('2026-12-02T21:30:00Z')));
        self::assertNotNull($active);
        self::assertSame('20:00', $active->end()->format());
        self::assertNull($promotion->activeWindowAt($tz->localize(new \DateTimeImmutable('2026-12-02T23:00:00Z'))));
    }

    public function test_update_changes_description_price_and_windows(): void
    {
        $promotion = $this->happyHour();
        $promotion->update(Money::ofCents(1400), 'Novo', Money::ofCents(900), [TimeWindow::fromStrings(5, '17:00', '19:00')]);

        self::assertSame('Novo', $promotion->description());
        self::assertSame(900, $promotion->price()->cents());
        self::assertSame(5, $promotion->windows()[0]->weekday()->value);
        self::assertSame(1, $promotion->id());
    }
}
