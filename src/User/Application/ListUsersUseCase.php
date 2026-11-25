<?php

declare(strict_types=1);

namespace Goomer\User\Application;

use Goomer\User\Domain\UserRepository;

final readonly class ListUsersUseCase
{
    public function __construct(private UserRepository $users) {}

    /**
     * @return list<UserOutput>
     */
    public function execute(int $restaurantId): array
    {
        return array_map(UserOutput::from(...), $this->users->ofRestaurant($restaurantId));
    }
}
