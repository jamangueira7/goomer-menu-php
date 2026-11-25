<?php

declare(strict_types=1);

namespace Goomer\Shared\Infrastructure;

use Goomer\Shared\Domain\Clock;

final class SystemClock implements Clock
{
    public function now(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }
}
