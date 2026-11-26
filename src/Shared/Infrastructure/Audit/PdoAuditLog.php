<?php

declare(strict_types=1);

namespace Goomer\Shared\Infrastructure\Audit;

use Goomer\Shared\Application\Audit\AuditEntry;
use Goomer\Shared\Application\Audit\AuditLog;
use Goomer\Shared\Domain\DateRange;
use Goomer\Shared\Infrastructure\Database\Database;
use Goomer\Shared\Infrastructure\Database\Row;

final readonly class PdoAuditLog implements AuditLog
{
    private const string INSTANT = 'Y-m-d H:i:s.uP';

    public function __construct(private Database $db) {}

    public function record(AuditEntry $entry): void
    {
        $this->db->execute(
            <<<'SQL'
                INSERT INTO audit_logs
                    (restaurant_id, actor_user_id, actor_email, action, entity_type, entity_id, changes, ip, created_at)
                VALUES
                    (:restaurant_id, :actor_user_id, :actor_email, :action, :entity_type, :entity_id,
                     CAST(:changes AS jsonb), CAST(:ip AS inet), CAST(:created_at AS timestamptz))
                SQL,
            [
                'restaurant_id' => $entry->restaurantId,
                'actor_user_id' => $entry->actorUserId,
                'actor_email' => $entry->actorEmail,
                'action' => $entry->action,
                'entity_type' => $entry->entityType,
                'entity_id' => $entry->entityId,
                'changes' => $entry->changesJson(),
                'ip' => $entry->ip,
                'created_at' => $entry->createdAt->format(self::INSTANT),
            ],
        );
    }

    public function ofActor(int $restaurantId, int $actorUserId, DateRange $range, int $page, int $perPage): array
    {
        $where = 'restaurant_id = :restaurant_id AND actor_user_id = :actor_user_id
                  AND created_at >= CAST(:from AS timestamptz) AND created_at < CAST(:until AS timestamptz)';
        $params = [
            'restaurant_id' => $restaurantId,
            'actor_user_id' => $actorUserId,
            'from' => $range->from()->format(self::INSTANT),
            'until' => $range->until()->format(self::INSTANT),
        ];

        $total = $this->db->fetchInt("SELECT count(*) FROM audit_logs WHERE {$where}", $params);
        $rows = $this->db->fetchAll(
            "SELECT id, restaurant_id, actor_user_id, actor_email, action, entity_type, entity_id, changes::text AS changes,
                    host(ip) AS ip, created_at
             FROM audit_logs WHERE {$where}
             ORDER BY created_at DESC, id DESC LIMIT :limit OFFSET :offset",
            [...$params, 'limit' => $perPage, 'offset' => ($page - 1) * $perPage],
        );

        return ['items' => array_map($this->hydrate(...), $rows), 'total' => $total];
    }

    public function lastEmailOf(int $restaurantId, int $actorUserId): ?string
    {
        return $this->db->fetchOne(
            'SELECT actor_email FROM audit_logs WHERE restaurant_id = :restaurant_id AND actor_user_id = :actor_user_id
             ORDER BY created_at DESC, id DESC LIMIT 1',
            ['restaurant_id' => $restaurantId, 'actor_user_id' => $actorUserId],
        )?->string('actor_email');
    }

    private function hydrate(Row $row): AuditEntry
    {
        $changes = json_decode($row->string('changes'), true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($changes)) {
            throw new \UnexpectedValueException('Coluna "changes" não é um objeto JSON.');
        }

        /** @var array<string, mixed> $changes */
        return new AuditEntry(
            $row->int('restaurant_id'),
            $row->int('actor_user_id'),
            $row->string('actor_email'),
            $row->string('action'),
            $row->string('entity_type'),
            $row->nullableInt('entity_id'),
            $changes,
            $row->nullableString('ip'),
            $row->dateTime('created_at'),
            $row->int('id'),
        );
    }
}
