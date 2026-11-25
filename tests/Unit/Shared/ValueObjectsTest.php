<?php

declare(strict_types=1);

namespace Goomer\Tests\Unit\Shared;

use Goomer\Shared\Domain\Exception\ValidationError;
use Goomer\Shared\Domain\Money;
use Goomer\Shared\Domain\TimeOfDay;
use Goomer\Shared\Domain\TimeWindow;
use Goomer\Shared\Domain\Timezone;
use Goomer\Shared\Domain\Weekday;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ValueObjectsTest extends TestCase
{
    public function test_money_is_in_cents_and_never_negative(): void
    {
        $price = Money::ofCents(1400);

        self::assertSame(1400, $price->cents());
        self::assertTrue(Money::ofCents(700)->isLessThan($price));
        self::assertFalse($price->isLessThan(Money::ofCents(1400)));
        self::assertTrue($price->equals(Money::ofCents(1400)));
        self::assertTrue(Money::ofCents(0)->isZero());

        $this->expectException(ValidationError::class);
        Money::ofCents(-1);
    }

    public function test_time_of_day_accepts_quarter_hours(): void
    {
        self::assertSame('18:45', TimeOfDay::fromString('18:45')->format());
        self::assertSame(0, TimeOfDay::fromString('00:00')->minutes());
        self::assertSame(23 * 60 + 45, TimeOfDay::fromString('23:45')->minutes());
    }

    #[DataProvider('invalidTimes')]
    public function test_time_of_day_rejects_invalid_values(string $value): void
    {
        $this->expectException(ValidationError::class);
        TimeOfDay::fromString($value);
    }

    /** @return iterable<string, array{string}> */
    public static function invalidTimes(): iterable
    {
        yield 'minutos fora do passo' => ['18:10'];
        yield 'hora 24' => ['24:00'];
        yield 'sem zero à esquerda' => ['8:00'];
        yield 'texto' => ['meio-dia'];
        yield 'segundos' => ['18:00:00'];
    }

    public function test_time_of_day_rejects_out_of_range_minutes(): void
    {
        $this->expectException(ValidationError::class);
        TimeOfDay::fromMinutes(24 * 60);
    }

    public function test_window_needs_end_after_start_and_rejects_midnight_crossing(): void
    {
        $window = TimeWindow::fromStrings(3, '18:00', '18:15');
        self::assertSame(Weekday::Wednesday, $window->weekday());
        self::assertSame('18:15', $window->end()->format());

        $this->expectException(ValidationError::class);
        TimeWindow::fromStrings(5, '22:00', '02:00');
    }

    public function test_window_rejects_empty_window(): void
    {
        $this->expectException(ValidationError::class);
        TimeWindow::fromStrings(1, '10:00', '10:00');
    }

    public function test_overlap_is_same_day_and_half_open(): void
    {
        $happyHour = TimeWindow::fromStrings(3, '18:00', '20:00');

        self::assertTrue($happyHour->overlaps(TimeWindow::fromStrings(3, '19:45', '21:00')));
        self::assertTrue($happyHour->overlaps(TimeWindow::fromStrings(3, '17:00', '18:15')));
        self::assertFalse($happyHour->overlaps(TimeWindow::fromStrings(3, '20:00', '21:00')), 'encostar não é sobrepor');
        self::assertFalse($happyHour->overlaps(TimeWindow::fromStrings(4, '18:00', '20:00')), 'outro dia');
        self::assertTrue(TimeWindow::anyOverlap([$happyHour, TimeWindow::fromStrings(3, '19:00', '19:30')]));
        self::assertFalse(TimeWindow::anyOverlap([$happyHour, TimeWindow::fromStrings(3, '20:00', '22:00')]));
    }

    public function test_window_contains_local_moment(): void
    {
        $tz = Timezone::fromString('America/Sao_Paulo');
        $window = TimeWindow::fromStrings(3, '18:00', '20:00');

        // 2026-12-02 é quarta-feira; 21:30Z = 18:30 em São Paulo (UTC-3).
        self::assertTrue($window->contains($tz->localize(new \DateTimeImmutable('2026-12-02T21:30:00Z'))));
        self::assertTrue($window->contains($tz->localize(new \DateTimeImmutable('2026-12-02T21:00:00Z'))), 'início inclusive');
        self::assertFalse($window->contains($tz->localize(new \DateTimeImmutable('2026-12-02T23:00:00Z'))), 'fim exclusive');
        self::assertFalse($window->contains($tz->localize(new \DateTimeImmutable('2026-12-03T21:30:00Z'))), 'quinta');
    }

    public function test_weekday_validation(): void
    {
        self::assertSame(Weekday::Saturday, Weekday::fromInt(6));
        $this->expectException(ValidationError::class);
        Weekday::fromInt(7);
    }

    public function test_timezone_localizes_and_validates(): void
    {
        $moment = Timezone::fromString('Asia/Tokyo')->localize(new \DateTimeImmutable('2026-12-02T21:30:00Z'));

        self::assertSame(Weekday::Thursday, $moment->weekday());
        self::assertSame('06:30:00', $moment->time());
        self::assertSame(6 * 60 + 30, $moment->minuteOfDay());
        self::assertSame('Asia/Tokyo', Timezone::fromString('Asia/Tokyo')->name());

        $this->expectException(ValidationError::class);
        Timezone::fromString('Mars/Olympus');
    }
}
