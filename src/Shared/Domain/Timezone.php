<?php

declare(strict_types=1);

namespace Goomer\Shared\Domain;

use Goomer\Shared\Domain\Exception\ValidationError;

/**
 * Fuso horário IANA do restaurante (ex.: America/Sao_Paulo).
 */
final readonly class Timezone
{
    private function __construct(private string $name) {}

    public static function fromString(string $name): self
    {
        if (!in_array($name, \DateTimeZone::listIdentifiers(), true)) {
            throw new ValidationError(sprintf('Fuso horário "%s" não está na lista IANA.', $name), 'timezone');
        }

        return new self($name);
    }

    public function name(): string
    {
        return $this->name;
    }

    public function localize(\DateTimeImmutable $instant): LocalMoment
    {
        return new LocalMoment($instant->setTimezone(new \DateTimeZone($this->name)));
    }
}
