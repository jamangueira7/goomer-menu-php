<?php

declare(strict_types=1);

namespace Goomer\Shared\Domain;

use Goomer\Shared\Domain\Exception\ValidationError;

/**
 * Período de dias inteiros no fuso do restaurante: do início do primeiro dia até o fim do último (inclusivo).
 * Guardado como [from, until), com `until` exclusivo, já em instantes absolutos.
 */
final readonly class DateRange
{
    private function __construct(
        private \DateTimeImmutable $from,
        private \DateTimeImmutable $until,
    ) {}

    /**
     * @param ?string $start dia inicial `AAAA-MM-DD` (obrigatório)
     * @param ?string $end   dia final `AAAA-MM-DD`; sem ele, vai até hoje (no fuso do restaurante)
     */
    public static function ofLocalDays(?string $start, ?string $end, Timezone $timezone, \DateTimeImmutable $now): self
    {
        $zone = new \DateTimeZone($timezone->name());
        if ($start === null) {
            throw new ValidationError('dateIni é obrigatório, no formato AAAA-MM-DD.', 'dateIni');
        }
        $from = self::day($start, 'dateIni', $zone);

        if ($end === null) {
            $last = $now->setTimezone($zone)->setTime(0, 0);
        } else {
            $last = self::day($end, 'dateEnd', $zone);
            if ($last < $from) {
                throw new ValidationError('dateEnd não pode ser anterior a dateIni.', 'dateEnd');
            }
        }

        return new self($from, $last->modify('+1 day'));
    }

    private static function day(string $value, string $field, \DateTimeZone $zone): \DateTimeImmutable
    {
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $m) !== 1 || !checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
            throw new ValidationError(sprintf('%s precisa ser uma data válida no formato AAAA-MM-DD.', $field), $field);
        }

        return new \DateTimeImmutable($value . ' 00:00:00', $zone);
    }

    /** Primeiro instante do período (inclusivo). */
    public function from(): \DateTimeImmutable
    {
        return $this->from;
    }

    /** Primeiro instante depois do período (exclusivo). */
    public function until(): \DateTimeImmutable
    {
        return $this->until;
    }

    public function contains(\DateTimeImmutable $instant): bool
    {
        return $instant >= $this->from && $instant < $this->until;
    }
}
