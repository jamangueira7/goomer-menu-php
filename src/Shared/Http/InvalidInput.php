<?php

declare(strict_types=1);

namespace Goomer\Shared\Http;

use Goomer\Shared\Domain\Exception\ValidationError;

/**
 * Corpo da requisição fora do formato esperado (HTTP 422, ou 400 para JSON malformado).
 */
final class InvalidInput extends ValidationError
{
    /**
     * @param array<string, string> $errors campo => mensagem
     */
    public function __construct(private readonly array $errors, private readonly bool $malformed = false)
    {
        parent::__construct($malformed ? 'O corpo da requisição não é um JSON válido.' : 'Dados inválidos.');
    }

    /** @return array<string, string> */
    public function errors(): array
    {
        return $this->errors;
    }

    public function isMalformed(): bool
    {
        return $this->malformed;
    }
}
