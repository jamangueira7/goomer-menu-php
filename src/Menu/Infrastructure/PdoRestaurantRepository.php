<?php

declare(strict_types=1);

namespace Goomer\Menu\Infrastructure;

use Goomer\Menu\Domain\Restaurant;
use Goomer\Menu\Domain\RestaurantRepository;
use Goomer\Shared\Domain\Timezone;
use Goomer\Shared\Infrastructure\Database\Database;

final readonly class PdoRestaurantRepository implements RestaurantRepository
{
    public function __construct(private Database $db) {}

    public function find(int $id): ?Restaurant
    {
        $row = $this->db->fetchOne('SELECT id, name, timezone FROM restaurants WHERE id = :id', ['id' => $id]);

        return $row === null ? null : new Restaurant(
            $row->int('id'),
            $row->string('name'),
            Timezone::fromString($row->string('timezone')),
        );
    }
}
