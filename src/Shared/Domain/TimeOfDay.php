<?php

declare(strict_types=1);

namespace Goomer\Shared\Domain;

use Goomer\Shared\Domain\Exception\ValidationError;

/**
 * Horário `HH:mm` de uma janela, com minutos de 15 em 15 (00, 15, 30 ou 45).
 */
final readonly class TimeOfDay
{
    public const int STEP_MINUTES = 15;

    private function __construct(private int $minutes) {}

    public static function fromString(string $value, string $field = 'time'): self
    {
        if (preg_match('/^([01]\d|2[0-3]):([0-5]\d)$/', $value, $m) !== 1) {
            throw new ValidationError(sprintf('Horário "%s" fora do formato HH:mm.', $value), $field);
        }

        return self::fromMinutes((int) $m[1] * 60 + (int) $m[2], $field);
    }

    public static function fromMinutes(int $minutes, string $field = 'time'): self
    {
        if ($minutes < 0 || $minutes >= 24 * 60) {
            throw new ValidationError('O horário precisa ficar entre 00:00 e 23:45.', $field);
        }
        if ($minutes % self::STEP_MINUTES !== 0) {
            throw new ValidationError('Os minutos só aceitam 00, 15, 30 ou 45.', $field);
        }

        return new self($minutes);
    }

    /** Minutos desde a meia-noite. */
    public function minutes(): int
    {
        return $this->minutes;
    }

    public function format(): string
    {
        return sprintf('%02d:%02d', intdiv($this->minutes, 60), $this->minutes % 60);
    }
}
