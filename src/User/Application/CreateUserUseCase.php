<?php

declare(strict_types=1);

namespace Goomer\User\Application;

use Goomer\Shared\Application\Audit\Actor;
use Goomer\Shared\Application\Audit\Auditor;
use Goomer\Shared\Application\TransactionManager;
use Goomer\User\Domain\Email;
use Goomer\User\Domain\EmailAlreadyTaken;
use Goomer\User\Domain\Password;
use Goomer\User\Domain\User;
use Goomer\User\Domain\UserRepository;

/**
 * Cadastra um usuário no restaurante: pela rota protegida (`execute`, ator = quem chamou) ou pelo seed e pelo
 * comando create-admin (`bootstrap`, sem ninguém logado: o ator registrado é o próprio usuário criado).
 */
final readonly class CreateUserUseCase
{
    public function __construct(
        private UserRepository $users,
        private PasswordHasher $hasher,
        private TransactionManager $transaction,
        private Auditor $audit,
    ) {}

    public function execute(Actor $actor, string $name, string $email, string $password): UserOutput
    {
        return $this->register($actor->restaurantId, $name, $email, $password, $actor);
    }

    public function bootstrap(int $restaurantId, string $name, string $email, string $password): UserOutput
    {
        return $this->register($restaurantId, $name, $email, $password, null);
    }

    private function register(int $restaurantId, string $name, string $email, string $password, ?Actor $actor): UserOutput
    {
        $address = Email::fromString($email);
        $secret = Password::fromString($password);
        if ($this->users->findByEmail($address) !== null) {
            throw EmailAlreadyTaken::of($address);
        }

        $user = User::register($this->users->nextId(), $restaurantId, $name, $address, $this->hasher->hash($secret));

        return $this->transaction->run(function () use ($user, $actor): UserOutput {
            $this->users->save($user);
            $this->audit->record(
                $actor ?? new Actor($user->id(), $user->email()->value(), $user->restaurantId()),
                'user.created',
                'user',
                $user->id(),
                ['after' => ['name' => $user->name(), 'email' => $user->email()->value()]],
            );

            return UserOutput::from($user);
        });
    }
}
