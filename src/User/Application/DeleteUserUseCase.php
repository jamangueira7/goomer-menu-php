<?php

declare(strict_types=1);

namespace Goomer\User\Application;

use Goomer\User\Domain\CannotDeleteSelf;
use Goomer\User\Domain\UserNotFound;
use Goomer\User\Domain\UserRepository;

final readonly class DeleteUserUseCase
{
    public function __construct(private UserRepository $users) {}

    public function execute(int $restaurantId, int $currentUserId, int $id): void
    {
        if ($id === $currentUserId) {
            throw CannotDeleteSelf::create();
        }
        $user = $this->users->find($restaurantId, $id) ?? throw UserNotFound::withId($id);
        $this->users->delete($user->id());
    }
}
