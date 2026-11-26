<?php

declare(strict_types=1);

namespace Goomer\Shared\Application\Audit;

use Goomer\Shared\Domain\DateRange;

/**
 * Porta do log de auditoria. Só grava (INSERT) e lê: nenhum caso de uso altera ou apaga log.
 */
interface AuditLog
{
    public function record(AuditEntry $entry): void;

    /**
     * Ações de um usuário no restaurante dentro do período, mais recentes primeiro.
     *
     * @return array{items: list<AuditEntry>, total: int}
     */
    public function ofActor(int $restaurantId, int $actorUserId, DateRange $range, int $page, int $perPage): array;

    /** E-mail mais recente do ator no log do restaurante (null se ele nunca fez nada lá). */
    public function lastEmailOf(int $restaurantId, int $actorUserId): ?string;
}
