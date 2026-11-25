<?php

declare(strict_types=1);

namespace Goomer\Shared\Domain;

use Goomer\Shared\Domain\Exception\ValidationError;

/**
 * Janela de um dia da semana, do início (inclusive) ao fim (exclusive).
 *
 * O fim fica pelo menos 15 minutos depois do início; janela que vira a meia-noite é rejeitada.
 */
final readonly class TimeWindow
{
    private function __construct(
        private Weekday $weekday,
        private TimeOfDay $start,
        private TimeOfDay $end,
    ) {}

    public static function of(Weekday $weekday, TimeOfDay $start, TimeOfDay $end): self
    {
        if ($end->minutes() <= $start->minutes()) {
            throw new ValidationError(sprintf(
                'A janela %s–%s vira a meia-noite ou termina antes de começar; cadastre uma janela em cada dia.',
                $start->format(),
                $end->format(),
            ), 'windows');
        }

        // Com minutos de 15 em 15 e fim depois do início, a duração mínima de 15 minutos já está garantida.
        return new self($weekday, $start, $end);
    }

    public static function fromStrings(int $weekday, string $start, string $end): self
    {
        return self::of(
            Weekday::fromInt($weekday),
            TimeOfDay::fromString($start, 'start'),
            TimeOfDay::fromString($end, 'end'),
        );
    }

    public function weekday(): Weekday
    {
        return $this->weekday;
    }

    public function start(): TimeOfDay
    {
        return $this->start;
    }

    public function end(): TimeOfDay
    {
        return $this->end;
    }

    public function overlaps(self $other): bool
    {
        return $this->weekday === $other->weekday
            && $this->start->minutes() < $other->end->minutes()
            && $other->start->minutes() < $this->end->minutes();
    }

    public function contains(LocalMoment $moment): bool
    {
        return $this->weekday === $moment->weekday()
            && $moment->minuteOfDay() >= $this->start->minutes()
            && $moment->minuteOfDay() < $this->end->minutes();
    }

    /**
     * @param list<self> $windows
     */
    public static function anyOverlap(array $windows): bool
    {
        foreach ($windows as $i => $a) {
            foreach (array_slice($windows, $i + 1) as $b) {
                if ($a->overlaps($b)) {
                    return true;
                }
            }
        }

        return false;
    }
}
