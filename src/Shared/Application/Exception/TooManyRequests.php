<?php

declare(strict_types=1);

namespace Goomer\Shared\Application\Exception;

/**
 * Limite de pedidos estourado (HTTP 429).
 */
final class TooManyRequests extends \RuntimeException
{
    public function __construct(string $message, private readonly int $retryAfterSeconds)
    {
        parent::__construct($message);
    }

    public function retryAfterSeconds(): int
    {
        return $this->retryAfterSeconds;
    }
}
