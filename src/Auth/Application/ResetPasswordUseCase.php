<?php

declare(strict_types=1);

namespace Goomer\Auth\Application;

use Goomer\Auth\Domain\InvalidResetToken;
use Goomer\Auth\Domain\PasswordResetToken;
use Goomer\Auth\Domain\PasswordResetTokenRepository;
use Goomer\Shared\Application\Audit\Actor;
use Goomer\Shared\Application\Audit\Auditor;
use Goomer\Shared\Application\TransactionManager;
use Goomer\Shared\Domain\Clock;
use Goomer\User\Application\PasswordHasher;
use Goomer\User\Domain\Password;
use Goomer\User\Domain\UserRepository;

/**
 * Troca a senha com o token do e-mail. JWTs já emitidos continuam valendo até vencer (no máximo 1 hora).
 */
final readonly class ResetPasswordUseCase
{
    public function __construct(
        private UserRepository $users,
        private PasswordResetTokenRepository $tokens,
        private PasswordHasher $hasher,
        private Clock $clock,
        private TransactionManager $transaction,
        private Auditor $audit,
    ) {}

    public function execute(string $plainToken, string $newPassword, ?string $ip = null): void
    {
        $password = Password::fromString($newPassword);
        $now = $this->clock->now();

        $token = $this->tokens->findByHash(PasswordResetToken::hash($plainToken)) ?? throw new InvalidResetToken();
        $token->consume($now);
        $user = $this->users->findById($token->userId()) ?? throw new InvalidResetToken();
        $user->changePassword($this->hasher->hash($password));

        $this->transaction->run(function () use ($user, $token, $ip): void {
            $this->users->save($user);
            $this->tokens->save($token);
            $this->audit->record(
                new Actor($user->id(), $user->email()->value(), $user->restaurantId(), $ip),
                'auth.password_reset',
                'user',
                $user->id(),
            );
        });
    }
}
