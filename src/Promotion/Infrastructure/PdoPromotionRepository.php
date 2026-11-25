<?php

declare(strict_types=1);

namespace Goomer\Promotion\Infrastructure;

use Goomer\Promotion\Domain\OverlappingPromotion;
use Goomer\Promotion\Domain\Promotion;
use Goomer\Promotion\Domain\PromotionRepository;
use Goomer\Shared\Domain\Money;
use Goomer\Shared\Domain\TimeWindow;
use Goomer\Shared\Infrastructure\Database\Database;
use Goomer\Shared\Infrastructure\Database\Row;

final readonly class PdoPromotionRepository implements PromotionRepository
{
    /** SQLSTATE de violação de restrição EXCLUDE no Postgres. */
    private const string EXCLUSION_VIOLATION = '23P01';

    public function __construct(private Database $db) {}

    public function nextId(): int
    {
        return $this->db->fetchInt("SELECT nextval(pg_get_serial_sequence('promotions', 'id'))");
    }

    public function save(Promotion $promotion): void
    {
        try {
            $this->db->transactional(function () use ($promotion): void {
                $this->db->execute(
                    <<<'SQL'
                        INSERT INTO promotions (id, product_id, description, promo_price_cents)
                        VALUES (:id, :product_id, :description, :price)
                        ON CONFLICT (id) DO UPDATE SET
                            description = EXCLUDED.description,
                            promo_price_cents = EXCLUDED.promo_price_cents,
                            updated_at = now()
                        SQL,
                    [
                        'id' => $promotion->id(),
                        'product_id' => $promotion->productId(),
                        'description' => $promotion->description(),
                        'price' => $promotion->price()->cents(),
                    ],
                );
                $this->db->execute('DELETE FROM promotion_windows WHERE promotion_id = :id', ['id' => $promotion->id()]);
                foreach ($promotion->windows() as $window) {
                    $this->db->execute(
                        'INSERT INTO promotion_windows (promotion_id, product_id, weekday, start_time, end_time)
                         VALUES (:promotion_id, :product_id, :weekday, CAST(:start AS time), CAST(:end AS time))',
                        [
                            'promotion_id' => $promotion->id(),
                            'product_id' => $promotion->productId(),
                            'weekday' => $window->weekday()->value,
                            'start' => $window->start()->format(),
                            'end' => $window->end()->format(),
                        ],
                    );
                }
            });
        } catch (\PDOException $e) {
            if ($e->getCode() === self::EXCLUSION_VIOLATION) {
                throw new OverlappingPromotion('Já existe outra promoção deste produto nesse dia e horário.', 0, $e);
            }
            throw $e;
        }
    }

    public function find(int $restaurantId, int $id): ?Promotion
    {
        $row = $this->db->fetchOne(
            'SELECT pr.id, pr.product_id, pr.description, pr.promo_price_cents
             FROM promotions pr JOIN products p ON p.id = pr.product_id
             WHERE pr.id = :id AND p.restaurant_id = :restaurant_id',
            ['id' => $id, 'restaurant_id' => $restaurantId],
        );

        return $row === null ? null : $this->hydrate([$row])[0];
    }

    public function ofProduct(int $productId): array
    {
        return $this->hydrate($this->db->fetchAll(
            'SELECT id, product_id, description, promo_price_cents FROM promotions WHERE product_id = :product_id ORDER BY id',
            ['product_id' => $productId],
        ));
    }

    public function ofRestaurant(int $restaurantId, ?int $productId): array
    {
        $sql = 'SELECT pr.id, pr.product_id, pr.description, pr.promo_price_cents
                FROM promotions pr JOIN products p ON p.id = pr.product_id
                WHERE p.restaurant_id = :restaurant_id';
        $params = ['restaurant_id' => $restaurantId];
        if ($productId !== null) {
            $sql .= ' AND pr.product_id = :product_id';
            $params['product_id'] = $productId;
        }

        return $this->hydrate($this->db->fetchAll($sql . ' ORDER BY pr.id', $params));
    }

    public function delete(int $id): void
    {
        $this->db->execute('DELETE FROM promotions WHERE id = :id', ['id' => $id]);
    }

    /**
     * @param list<Row> $rows
     *
     * @return list<Promotion>
     */
    private function hydrate(array $rows): array
    {
        if ($rows === []) {
            return [];
        }
        $ids = array_map(static fn(Row $row): int => $row->int('id'), $rows);

        /** @var array<int, list<TimeWindow>> $windows */
        $windows = [];
        $windowRows = $this->db->fetchAll(
            'SELECT promotion_id, weekday, start_time, end_time FROM promotion_windows
             WHERE promotion_id = ANY(CAST(:ids AS bigint[])) ORDER BY weekday, start_time',
            ['ids' => Database::intArray($ids)],
        );
        foreach ($windowRows as $row) {
            $windows[$row->int('promotion_id')][] = $row->timeWindow();
        }

        return array_map(static fn(Row $row): Promotion => Promotion::restore(
            $row->int('id'),
            $row->int('product_id'),
            $row->string('description'),
            Money::ofCents($row->int('promo_price_cents')),
            $windows[$row->int('id')] ?? [],
        ), $rows);
    }
}
