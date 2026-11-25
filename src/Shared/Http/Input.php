<?php

declare(strict_types=1);

namespace Goomer\Shared\Http;

use Psr\Http\Message\ServerRequestInterface;

/**
 * Leitura tipada do corpo JSON. Só valida formato; regra de negócio fica no domínio.
 */
final class Input
{
    /** @var array<string, string> */
    private array $errors = [];

    /**
     * @param array<array-key, mixed> $data
     */
    private function __construct(private readonly array $data) {}

    public static function fromRequest(ServerRequestInterface $request): self
    {
        $body = (string) $request->getBody();
        if (trim($body) === '') {
            return new self([]);
        }
        try {
            $data = json_decode($body, true, 32, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new InvalidInput([], malformed: true);
        }
        if (!is_array($data)) {
            throw new InvalidInput(['body' => 'O corpo precisa ser um objeto JSON.']);
        }

        return new self($data);
    }

    public function string(string $field): string
    {
        $value = $this->data[$field] ?? null;
        if (!is_string($value)) {
            $this->errors[$field] = 'Obrigatório, do tipo texto.';

            return '';
        }

        return $value;
    }

    public function int(string $field): int
    {
        $value = $this->data[$field] ?? null;
        if (!is_int($value)) {
            $this->errors[$field] = 'Obrigatório, do tipo inteiro.';

            return 0;
        }

        return $value;
    }

    public function bool(string $field, ?bool $default = null): bool
    {
        $value = $this->data[$field] ?? $default;
        if (!is_bool($value)) {
            $this->errors[$field] = 'Obrigatório, do tipo booleano.';

            return false;
        }

        return $value;
    }

    /**
     * @return list<int>
     */
    public function intList(string $field): array
    {
        $value = $this->data[$field] ?? null;
        if (!is_array($value) || !array_is_list($value) || array_filter($value, is_int(...)) !== $value) {
            $this->errors[$field] = 'Obrigatório, uma lista de inteiros.';

            return [];
        }

        /** @var list<int> $value */
        return $value;
    }

    /**
     * Lista de janelas `{weekday, start, end}`.
     *
     * @return list<array{weekday: int, start: string, end: string}>
     */
    public function windows(string $field, bool $required): array
    {
        $value = $this->data[$field] ?? null;
        if ($value === null && !$required) {
            return [];
        }
        if (!is_array($value) || !array_is_list($value)) {
            $this->errors[$field] = 'Precisa ser uma lista de janelas {weekday, start, end}.';

            return [];
        }

        $windows = [];
        foreach ($value as $i => $window) {
            if (!is_array($window) || !is_int($window['weekday'] ?? null)
                || !is_string($window['start'] ?? null) || !is_string($window['end'] ?? null)) {
                $this->errors["{$field}[{$i}]"] = 'Janela precisa de weekday (inteiro), start e end (HH:mm).';

                continue;
            }
            $windows[] = ['weekday' => $window['weekday'], 'start' => $window['start'], 'end' => $window['end']];
        }

        return $windows;
    }

    /** Lança InvalidInput se algum campo lido estava fora do formato. */
    public function validate(): void
    {
        if ($this->errors !== []) {
            throw new InvalidInput($this->errors);
        }
    }
}
