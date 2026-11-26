<?php

declare(strict_types=1);

namespace Goomer\User\Application;

use Goomer\Shared\Application\Audit\AuditEntry;
use Goomer\Shared\Domain\Timezone;

/**
 * Página do log de auditoria de um usuário, com as horas no fuso do restaurante.
 */
final readonly class AuditLogPage
{
    /**
     * @param list<AuditEntry> $items
     */
    public function __construct(
        public array $items,
        public Timezone $timezone,
        public int $userId,
        public string $userEmail,
        public int $page,
        public int $perPage,
        public int $total,
    ) {}

    /**
     * @return array{data: list<array<string, mixed>>, user: array{id: int, email: string}, meta: array{page: int, perPage: int, total: int}}
     */
    public function toArray(): array
    {
        return [
            'data' => array_map(fn(AuditEntry $entry): array => [
                'id' => $entry->id,
                'action' => $entry->action,
                'entityType' => $entry->entityType,
                'entityId' => $entry->entityId,
                'changes' => $entry->changesAsObject(),
                'ip' => $entry->ip,
                'createdAt' => $this->timezone->localize($entry->createdAt)->dateTime()->format(\DATE_ATOM),
            ], $this->items),
            'user' => ['id' => $this->userId, 'email' => $this->userEmail],
            'meta' => ['page' => $this->page, 'perPage' => $this->perPage, 'total' => $this->total],
        ];
    }
}
