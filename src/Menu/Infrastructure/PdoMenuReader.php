<?php

declare(strict_types=1);

namespace Goomer\Menu\Infrastructure;

use Goomer\Menu\Application\MenuItem;
use Goomer\Menu\Application\MenuReader;
use Goomer\Shared\Domain\LocalMoment;
use Goomer\Shared\Infrastructure\Database\Database;
use Goomer\Shared\Infrastructure\Database\Row;

/**
 * O cardápio sai de uma única consulta: visibilidade, disponibilidade, promoção ativa (LEFT JOIN LATERAL)
 * e ordenação por categoria (ordem do ENUM), posição e nome.
 */
final readonly class PdoMenuReader implements MenuReader
{
    private const string SQL = <<<'SQL'
        WITH now_local AS (
            SELECT CAST(:weekday AS smallint) AS weekday, CAST(:time AS time) AS t
        )
        SELECT p.id, p.name, p.category, p.price_cents,
               pr.description AS promo_description, pr.promo_price_cents,
               to_char(w.end_time, 'HH24:MI') AS promo_ends_at
        FROM products p
        CROSS JOIN now_local n
        LEFT JOIN LATERAL (
            SELECT pw.promotion_id, pw.end_time
            FROM promotion_windows pw
            WHERE pw.product_id = p.id AND pw.weekday = n.weekday
              AND n.t >= pw.start_time AND n.t < pw.end_time
            LIMIT 1
        ) w ON TRUE
        LEFT JOIN promotions pr ON pr.id = w.promotion_id
        WHERE p.restaurant_id = :restaurant_id
          AND p.visible
          AND (NOT EXISTS (SELECT 1 FROM product_availability a WHERE a.product_id = p.id)
               OR EXISTS (SELECT 1 FROM product_availability a
                          WHERE a.product_id = p.id AND a.weekday = n.weekday
                            AND n.t >= a.start_time AND n.t < a.end_time))
        ORDER BY p.category, p.position, p.name
        SQL;

    public function __construct(private Database $db) {}

    public function itemsAt(int $restaurantId, LocalMoment $moment): array
    {
        $rows = $this->db->fetchAll(self::SQL, [
            'restaurant_id' => $restaurantId,
            'weekday' => $moment->weekday()->value,
            'time' => $moment->time(),
        ]);

        return array_map(static fn(Row $row): MenuItem => new MenuItem(
            $row->int('id'),
            $row->string('name'),
            $row->string('category'),
            $row->int('price_cents'),
            $row->nullableString('promo_description'),
            $row->nullableInt('promo_price_cents'),
            $row->nullableString('promo_ends_at'),
        ), $rows);
    }
}
