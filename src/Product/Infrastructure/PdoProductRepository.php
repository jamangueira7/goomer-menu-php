<?php

declare(strict_types=1);

namespace Goomer\Product\Infrastructure;

use Goomer\Product\Domain\Category;
use Goomer\Product\Domain\Product;
use Goomer\Product\Domain\ProductRepository;
use Goomer\Shared\Domain\Money;
use Goomer\Shared\Domain\TimeWindow;
use Goomer\Shared\Infrastructure\Database\Database;
use Goomer\Shared\Infrastructure\Database\Row;

final readonly class PdoProductRepository implements ProductRepository
{
    private const string COLUMNS = 'id, restaurant_id, name, price_cents, category, visible, position';

    public function __construct(private Database $db) {}

    public function nextId(): int
    {
        return $this->db->fetchInt("SELECT nextval(pg_get_serial_sequence('products', 'id'))");
    }

    public function save(Product $product): void
    {
        $this->db->transactional(function () use ($product): void {
            $this->db->execute(
                <<<'SQL'
                    INSERT INTO products (id, restaurant_id, name, price_cents, category, visible, position)
                    VALUES (:id, :restaurant_id, :name, :price_cents, CAST(:category AS product_category), :visible, :position)
                    ON CONFLICT (id) DO UPDATE SET
                        name = EXCLUDED.name,
                        price_cents = EXCLUDED.price_cents,
                        category = EXCLUDED.category,
                        visible = EXCLUDED.visible,
                        position = EXCLUDED.position,
                        updated_at = now()
                    SQL,
                [
                    'id' => $product->id(),
                    'restaurant_id' => $product->restaurantId(),
                    'name' => $product->name(),
                    'price_cents' => $product->price()->cents(),
                    'category' => $product->category()->value,
                    'visible' => $product->isVisible(),
                    'position' => $product->position(),
                ],
            );
            $this->db->execute('DELETE FROM product_availability WHERE product_id = :id', ['id' => $product->id()]);
            foreach ($product->availability() as $window) {
                $this->db->execute(
                    'INSERT INTO product_availability (product_id, weekday, start_time, end_time)
                     VALUES (:product_id, :weekday, CAST(:start AS time), CAST(:end AS time))',
                    [
                        'product_id' => $product->id(),
                        'weekday' => $window->weekday()->value,
                        'start' => $window->start()->format(),
                        'end' => $window->end()->format(),
                    ],
                );
            }
        });
    }

    public function find(int $restaurantId, int $id): ?Product
    {
        $row = $this->db->fetchOne(
            'SELECT ' . self::COLUMNS . ' FROM products WHERE id = :id AND restaurant_id = :restaurant_id',
            ['id' => $id, 'restaurant_id' => $restaurantId],
        );

        return $row === null ? null : $this->hydrate([$row])[0];
    }

    public function search(int $restaurantId, ?Category $category, ?bool $visible, int $page, int $perPage): array
    {
        $where = 'restaurant_id = :restaurant_id';
        $params = ['restaurant_id' => $restaurantId];
        if ($category !== null) {
            $where .= ' AND category = CAST(:category AS product_category)';
            $params['category'] = $category->value;
        }
        if ($visible !== null) {
            $where .= ' AND visible = :visible';
            $params['visible'] = $visible;
        }

        $total = $this->db->fetchInt("SELECT count(*) FROM products WHERE {$where}", $params);
        $rows = $this->db->fetchAll(
            'SELECT ' . self::COLUMNS . " FROM products WHERE {$where} ORDER BY position, id LIMIT :limit OFFSET :offset",
            [...$params, 'limit' => $perPage, 'offset' => ($page - 1) * $perPage],
        );

        return ['items' => $this->hydrate($rows), 'total' => $total];
    }

    public function lastPosition(int $restaurantId): int
    {
        return $this->db->fetchInt(
            'SELECT COALESCE(max(position), 0) FROM products WHERE restaurant_id = :restaurant_id',
            ['restaurant_id' => $restaurantId],
        );
    }

    public function idsOf(int $restaurantId): array
    {
        return array_map(
            static fn(Row $row): int => $row->int('id'),
            $this->db->fetchAll(
                'SELECT id FROM products WHERE restaurant_id = :restaurant_id ORDER BY position, id',
                ['restaurant_id' => $restaurantId],
            ),
        );
    }

    public function savePositions(int $restaurantId, array $positions): void
    {
        $this->db->transactional(function () use ($restaurantId, $positions): void {
            foreach ($positions as $id => $position) {
                $this->db->execute(
                    'UPDATE products SET position = :position, updated_at = now() WHERE id = :id AND restaurant_id = :restaurant_id',
                    ['position' => $position, 'id' => $id, 'restaurant_id' => $restaurantId],
                );
            }
        });
    }

    public function delete(int $restaurantId, int $id): void
    {
        $this->db->execute(
            'DELETE FROM products WHERE id = :id AND restaurant_id = :restaurant_id',
            ['id' => $id, 'restaurant_id' => $restaurantId],
        );
    }

    /**
     * @param list<Row> $rows
     *
     * @return list<Product>
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
            'SELECT product_id, weekday, start_time, end_time FROM product_availability
             WHERE product_id = ANY(CAST(:ids AS bigint[])) ORDER BY weekday, start_time',
            ['ids' => Database::intArray($ids)],
        );
        foreach ($windowRows as $row) {
            $windows[$row->int('product_id')][] = $row->timeWindow();
        }

        return array_map(static fn(Row $row): Product => Product::restore(
            $row->int('id'),
            $row->int('restaurant_id'),
            $row->string('name'),
            Money::ofCents($row->int('price_cents')),
            Category::from($row->string('category')),
            $row->bool('visible'),
            $row->int('position'),
            $windows[$row->int('id')] ?? [],
        ), $rows);
    }
}
