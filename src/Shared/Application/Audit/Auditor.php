<?php

declare(strict_types=1);

namespace Goomer\Shared\Application\Audit;

use Goomer\Shared\Domain\Clock;

/**
 * Registra uma ação no log com a hora do `Clock`. Os casos de uso chamam dentro da mesma transação da escrita.
 */
final readonly class Auditor
{
    public function __construct(
        private AuditLog $log,
        private Clock $clock,
    ) {}

    /**
     * @param array<string, mixed> $changes
     */
    public function record(Actor $actor, string $action, string $entityType, ?int $entityId, array $changes = []): void
    {
        $this->log->record(AuditEntry::by($actor, $action, $entityType, $entityId, $changes, $this->clock->now()));
    }

    /**
     * De/para só com os campos que mudaram.
     *
     * @param array<string, mixed> $before
     * @param array<string, mixed> $after
     *
     * @return array{before: array<string, mixed>, after: array<string, mixed>}
     */
    public static function diff(array $before, array $after): array
    {
        $old = [];
        $new = [];
        foreach ($after as $field => $value) {
            if (!array_key_exists($field, $before) || $before[$field] !== $value) {
                $old[$field] = $before[$field] ?? null;
                $new[$field] = $value;
            }
        }

        return ['before' => $old, 'after' => $new];
    }
}
