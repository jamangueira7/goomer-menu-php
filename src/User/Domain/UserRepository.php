<?php

declare(strict_types=1);

namespace Goomer\User\Domain;

interface UserRepository
{
    public function nextId(): int;

    /** Lança EmailAlreadyTaken se o banco recusar o e-mail repetido. */
    public function save(User $user): void;

    public function findByEmail(Email $email): ?User;

    public function findById(int $id): ?User;

    /** Só devolve o usuário se ele for do restaurante informado. */
    public function find(int $restaurantId, int $id): ?User;

    /** @return list<User> */
    public function ofRestaurant(int $restaurantId): array;

    public function delete(int $id): void;
}
