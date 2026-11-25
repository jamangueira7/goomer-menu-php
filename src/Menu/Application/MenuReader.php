<?php

declare(strict_types=1);

namespace Goomer\Menu\Application;

use Goomer\Shared\Domain\LocalMoment;

/**
 * Consulta de leitura do cardápio (no Postgres, uma única query com LEFT JOIN LATERAL).
 */
interface MenuReader
{
    /**
     * Produtos visíveis e disponíveis no momento local, ordenados por categoria, posição e nome.
     *
     * @return list<MenuItem>
     */
    public function itemsAt(int $restaurantId, LocalMoment $moment): array;
}
