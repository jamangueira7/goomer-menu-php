<?php

declare(strict_types=1);

namespace Goomer\Tests\Support;

use Goomer\User\Domain\Email;
use Goomer\User\Domain\User;
use Goomer\User\Domain\UserRepository;

final class InMemoryUserRepository implements UserRepository
{
    /** @var array<int, User> */
    public array $items = [];
    private int $sequence = 0;

    public function nextId(): int
    {
        return ++$this->sequence;
    }

    public function save(User $user): void
    {
        $this->items[$user->id()] = clone $user;
    }

    public function findByEmail(Email $email): ?User
    {
        foreach ($this->items as $user) {
            if ($user->email()->value() === $email->value()) {
                return clone $user;
            }
        }

        return null;
    }

    public function findById(int $id): ?User
    {
        return isset($this->items[$id]) ? clone $this->items[$id] : null;
    }

    public function find(int $restaurantId, int $id): ?User
    {
        $user = $this->findById($id);

        return $user !== null && $user->restaurantId() === $restaurantId ? $user : null;
    }

    public function ofRestaurant(int $restaurantId): array
    {
        return array_values(array_filter($this->items, static fn(User $u): bool => $u->restaurantId() === $restaurantId));
    }

    public function delete(int $id): void
    {
        unset($this->items[$id]);
    }
}
