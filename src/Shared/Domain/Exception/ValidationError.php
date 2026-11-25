<?php

declare(strict_types=1);

namespace Goomer\Shared\Domain\Exception;

/**
 * Dado que viola uma regra de negócio (HTTP 422).
 */
class ValidationError extends DomainError
{
    public function __construct(string $message, private readonly ?string $field = null)
    {
        parent::__construct($message);
    }

    public function field(): ?string
    {
        return $this->field;
    }
}
