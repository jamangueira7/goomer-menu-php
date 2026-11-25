<?php

declare(strict_types=1);

namespace Goomer\Shared\Domain;

/**
 * Relógio injetável: a hora "agora" nunca vem de `new DateTimeImmutable()` espalhado pelo código.
 */
interface Clock
{
    public function now(): \DateTimeImmutable;
}
