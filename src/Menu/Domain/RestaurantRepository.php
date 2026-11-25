<?php

declare(strict_types=1);

namespace Goomer\Menu\Domain;

interface RestaurantRepository
{
    public function find(int $id): ?Restaurant;
}
