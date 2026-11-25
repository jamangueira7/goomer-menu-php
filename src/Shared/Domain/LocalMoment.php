<?php

declare(strict_types=1);

namespace Goomer\Shared\Domain;

/**
 * Dia da semana e hora locais de um restaurante (minutos livres, não só de 15 em 15).
 */
final readonly class LocalMoment
{
    public function __construct(private \DateTimeImmutable $local) {}

    public function weekday(): Weekday
    {
        return Weekday::of($this->local);
    }

    public function minuteOfDay(): int
    {
        return (int) $this->local->format('G') * 60 + (int) $this->local->format('i');
    }

    /** Hora local no formato `HH:mm:ss`, pronta para comparar com colunas `time`. */
    public function time(): string
    {
        return $this->local->format('H:i:s');
    }

    public function dateTime(): \DateTimeImmutable
    {
        return $this->local;
    }
}
