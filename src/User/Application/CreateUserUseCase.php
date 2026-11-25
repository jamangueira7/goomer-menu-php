<?php

declare(strict_types=1);

namespace Goomer\User\Application;

use Goomer\User\Domain\Email;
use Goomer\User\Domain\EmailAlreadyTaken;
use Goomer\User\Domain\Password;
use Goomer\User\Domain\User;
use Goomer\User\Domain\UserRepository;

/**
 * Cadastra um usuário no restaurante (usado pela rota protegida e pelo comando create-admin).
 */
final readonly class CreateUserUseCase
{
    public function __construct(
        private UserRepository $users,
        private PasswordHasher $hasher,
    ) {}

    public function execute(int $restaurantId, string $name, string $email, string $password): UserOutput
    {
        $address = Email::fromString($email);
        $secret = Password::fromString($password);
        if ($this->users->findByEmail($address) !== null) {
            throw EmailAlreadyTaken::of($address);
        }

        $user = User::register($this->users->nextId(), $restaurantId, $name, $address, $this->hasher->hash($secret));
        $this->users->save($user);

        return UserOutput::from($user);
    }
}
