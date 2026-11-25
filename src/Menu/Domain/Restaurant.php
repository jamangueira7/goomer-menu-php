<?php

declare(strict_types=1);

namespace Goomer\Menu\Domain;

use Goomer\Shared\Domain\Timezone;

final readonly class Restaurant
{
    public function __construct(
        private int $id,
        private string $name,
        private Timezone $timezone,
    ) {}

    public function id(): int
    {
        return $this->id;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function timezone(): Timezone
    {
        return $this->timezone;
    }
}
