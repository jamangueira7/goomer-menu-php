<?php

declare(strict_types=1);

namespace Goomer\User\Application;

use Goomer\Shared\Application\Audit\Actor;
use Goomer\Shared\Application\Audit\Auditor;
use Goomer\Shared\Application\TransactionManager;
use Goomer\User\Domain\CannotDeleteSelf;
use Goomer\User\Domain\UserNotFound;
use Goomer\User\Domain\UserRepository;

final readonly class DeleteUserUseCase
{
    public function __construct(
        private UserRepository $users,
        private TransactionManager $transaction,
        private Auditor $audit,
    ) {}

    public function execute(Actor $actor, int $id): void
    {
        if ($id === $actor->userId) {
            throw CannotDeleteSelf::create();
        }
        $user = $this->users->find($actor->restaurantId, $id) ?? throw UserNotFound::withId($id);

        $this->transaction->run(function () use ($actor, $user): void {
            $this->users->delete($user->id());
            $this->audit->record($actor, 'user.deleted', 'user', $user->id(), [
                'before' => ['name' => $user->name(), 'email' => $user->email()->value()],
            ]);
        });
    }
}
