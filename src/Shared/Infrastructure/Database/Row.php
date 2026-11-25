<?php

declare(strict_types=1);

namespace Goomer\Shared\Infrastructure\Database;

use Goomer\Shared\Domain\TimeWindow;

/**
 * Linha do banco com leitura tipada das colunas.
 */
final readonly class Row
{
    /**
     * @param array<array-key, mixed> $values
     */
    private function __construct(private array $values) {}

    public static function from(mixed $row): self
    {
        if (!is_array($row)) {
            throw new \UnexpectedValueException('Linha do banco não é um array.');
        }

        return new self($row);
    }

    public function int(string $column): int
    {
        $value = $this->values[$column] ?? null;
        if (!is_numeric($value)) {
            throw new \UnexpectedValueException(sprintf('Coluna "%s" não é numérica.', $column));
        }

        return (int) $value;
    }

    public function string(string $column): string
    {
        $value = $this->values[$column] ?? null;
        if (!is_scalar($value)) {
            throw new \UnexpectedValueException(sprintf('Coluna "%s" não é texto.', $column));
        }

        return (string) $value;
    }

    public function nullableString(string $column): ?string
    {
        return ($this->values[$column] ?? null) === null ? null : $this->string($column);
    }

    public function nullableInt(string $column): ?int
    {
        return ($this->values[$column] ?? null) === null ? null : $this->int($column);
    }

    public function bool(string $column): bool
    {
        $value = $this->values[$column] ?? null;

        return $value === true || $value === 't' || $value === 1 || $value === '1';
    }

    public function dateTime(string $column): \DateTimeImmutable
    {
        return new \DateTimeImmutable($this->string($column));
    }

    public function nullableDateTime(string $column): ?\DateTimeImmutable
    {
        return ($this->values[$column] ?? null) === null ? null : $this->dateTime($column);
    }

    /** Converte colunas `weekday`, `start_time` e `end_time` (HH:MM:SS) numa janela. */
    public function timeWindow(): TimeWindow
    {
        return TimeWindow::fromStrings(
            $this->int('weekday'),
            substr($this->string('start_time'), 0, 5),
            substr($this->string('end_time'), 0, 5),
        );
    }
}
