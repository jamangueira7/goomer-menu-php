<?php

declare(strict_types=1);

namespace Goomer\Tests\Unit\Shared;

use Goomer\Shared\Domain\DateRange;
use Goomer\Shared\Domain\Exception\ValidationError;
use Goomer\Shared\Domain\Timezone;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class DateRangeTest extends TestCase
{
    private const string UTC = 'Y-m-d\TH:i:s\Z';

    private static function utc(\DateTimeImmutable $instant): string
    {
        return $instant->setTimezone(new \DateTimeZone('UTC'))->format(self::UTC);
    }

    private static function now(string $instant = '2026-12-02T21:30:00Z'): \DateTimeImmutable
    {
        return new \DateTimeImmutable($instant);
    }

    public function test_days_are_inclusive_and_in_restaurant_timezone(): void
    {
        $range = DateRange::ofLocalDays('2026-11-25', '2026-11-26', Timezone::fromString('America/Sao_Paulo'), self::now());

        self::assertSame('2026-11-25T03:00:00Z', self::utc($range->from()));
        self::assertSame('2026-11-27T03:00:00Z', self::utc($range->until()));
        self::assertTrue($range->contains(new \DateTimeImmutable('2026-11-27T02:59:59Z')), '26/11 23:59:59 em São Paulo');
        self::assertFalse($range->contains(new \DateTimeImmutable('2026-11-27T03:00:00Z')), '27/11 00:00 em São Paulo');
        self::assertFalse($range->contains(new \DateTimeImmutable('2026-11-25T02:59:59Z')), '24/11 23:59:59 em São Paulo');
    }

    public function test_same_day_in_tokyo(): void
    {
        $range = DateRange::ofLocalDays('2026-11-25', '2026-11-25', Timezone::fromString('Asia/Tokyo'), self::now());

        self::assertSame('2026-11-24T15:00:00Z', self::utc($range->from()));
        self::assertSame('2026-11-25T15:00:00Z', self::utc($range->until()));
    }

    public function test_without_end_goes_until_end_of_local_today(): void
    {
        $saoPaulo = Timezone::fromString('America/Sao_Paulo');

        $range = DateRange::ofLocalDays('2026-11-25', null, $saoPaulo, self::now());
        self::assertSame('2026-12-03T03:00:00Z', self::utc($range->until()));

        // 01:00 UTC do dia 3 ainda é dia 2 (22:00) em São Paulo.
        $lateNight = DateRange::ofLocalDays('2026-12-02', null, $saoPaulo, self::now('2026-12-03T01:00:00Z'));
        self::assertSame('2026-12-03T03:00:00Z', self::utc($lateNight->until()));
        self::assertTrue($lateNight->contains(self::now('2026-12-03T01:00:00Z')));
    }

    /**
     * @return iterable<string, array{?string, ?string, string}>
     */
    public static function invalidRanges(): iterable
    {
        yield 'sem dateIni' => [null, null, 'dateIni'];
        yield 'dateIni em outro formato' => ['25/11/2026', null, 'dateIni'];
        yield 'dateIni que não existe' => ['2026-02-30', null, 'dateIni'];
        yield 'dateEnd com lixo' => ['2026-11-25', 'amanhã', 'dateEnd'];
        yield 'dateEnd antes de dateIni' => ['2026-11-25', '2026-11-24', 'dateEnd'];
    }

    #[DataProvider('invalidRanges')]
    public function test_invalid_dates_name_the_field(?string $start, ?string $end, string $field): void
    {
        try {
            DateRange::ofLocalDays($start, $end, Timezone::fromString('America/Sao_Paulo'), self::now());
            self::fail('Era para recusar');
        } catch (ValidationError $e) {
            self::assertSame($field, $e->field());
        }
    }
}
