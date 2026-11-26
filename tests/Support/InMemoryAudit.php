<?php

declare(strict_types=1);

namespace Goomer\Tests\Support;

use Goomer\Shared\Application\Audit\Actor;
use Goomer\Shared\Application\Audit\AuditEntry;
use Goomer\Shared\Application\Audit\AuditLog;
use Goomer\Shared\Application\Audit\Auditor;
use Goomer\Shared\Application\TransactionManager;
use Goomer\Shared\Domain\Clock;
use Goomer\Shared\Domain\DateRange;

/**
 * Log de auditoria e transação em memória. A transação só executa e conta quantas vezes abriu.
 */
final class InMemoryAudit implements AuditLog, TransactionManager
{
    /** @var list<AuditEntry> */
    public array $entries = [];
    public int $transactions = 0;

    public function __construct(private readonly Clock $clock = new FixedClock()) {}

    public static function actor(int $userId = 1, int $restaurantId = 1, string $email = 'admin@bar.com'): Actor
    {
        return new Actor($userId, $email, $restaurantId, '10.0.0.1');
    }

    public function auditor(): Auditor
    {
        return new Auditor($this, $this->clock);
    }

    public function run(callable $work): mixed
    {
        ++$this->transactions;

        return $work();
    }

    public function record(AuditEntry $entry): void
    {
        $this->entries[] = $entry->withId(count($this->entries) + 1);
    }

    public function ofActor(int $restaurantId, int $actorUserId, DateRange $range, int $page, int $perPage): array
    {
        $matches = array_values(array_filter(
            $this->entries,
            static fn(AuditEntry $e): bool => $e->restaurantId === $restaurantId && $e->actorUserId === $actorUserId
                && $range->contains($e->createdAt),
        ));
        usort($matches, static fn(AuditEntry $a, AuditEntry $b): int => [$b->createdAt, $b->id] <=> [$a->createdAt, $a->id]);

        return ['items' => array_slice($matches, ($page - 1) * $perPage, $perPage), 'total' => count($matches)];
    }

    public function lastEmailOf(int $restaurantId, int $actorUserId): ?string
    {
        $email = null;
        foreach ($this->entries as $entry) {
            if ($entry->restaurantId === $restaurantId && $entry->actorUserId === $actorUserId) {
                $email = $entry->actorEmail;
            }
        }

        return $email;
    }

    public function last(): AuditEntry
    {
        $last = end($this->entries);

        return $last !== false ? $last : throw new \LogicException('Nenhuma entrada no log.');
    }

    /** @return list<string> */
    public function actions(): array
    {
        return array_map(static fn(AuditEntry $e): string => $e->action, $this->entries);
    }
}
