<?php

declare(strict_types=1);

namespace Goomer\Auth\Application;

use Goomer\Auth\Domain\PasswordResetToken;
use Goomer\Auth\Domain\PasswordResetTokenRepository;
use Goomer\Shared\Application\Audit\Actor;
use Goomer\Shared\Application\Audit\Auditor;
use Goomer\Shared\Application\Exception\TooManyRequests;
use Goomer\Shared\Application\TransactionManager;
use Goomer\Shared\Domain\Clock;
use Goomer\User\Domain\Email;
use Goomer\User\Domain\UserRepository;

/**
 * Envia o link de redefinição. A resposta é a mesma exista ou não o e-mail; o limite vale para ambos.
 * Só o pedido para e-mail existente entra no log de auditoria (o ator é o próprio dono do e-mail).
 */
final readonly class ForgotPasswordUseCase
{
    public const int MAX_REQUESTS = 5;
    public const int WINDOW_MINUTES = 15;

    public function __construct(
        private UserRepository $users,
        private PasswordResetTokenRepository $tokens,
        private ResetRequestThrottle $throttle,
        private Mailer $mailer,
        private Clock $clock,
        private TransactionManager $transaction,
        private Auditor $audit,
        private string $resetUrl,
    ) {}

    public function execute(string $email, string $ip): void
    {
        $address = Email::fromString($email);
        $now = $this->clock->now();
        $since = $now->modify(sprintf('-%d minutes', self::WINDOW_MINUTES));

        if ($this->throttle->countByEmailSince($address->value(), $since) >= self::MAX_REQUESTS
            || $this->throttle->countByIpSince($ip, $since) >= self::MAX_REQUESTS) {
            throw new TooManyRequests('Muitos pedidos de recuperação. Tente de novo em alguns minutos.', self::WINDOW_MINUTES * 60);
        }
        $this->throttle->record($address->value(), $ip, $now);

        $user = $this->users->findByEmail($address);
        if ($user === null) {
            return;
        }

        $plain = bin2hex(random_bytes(32));
        $actor = new Actor(
            $user->id(),
            $user->email()->value(),
            $user->restaurantId(),
            filter_var($ip, FILTER_VALIDATE_IP) !== false ? $ip : null,
        );
        $this->transaction->run(function () use ($user, $plain, $now, $actor): void {
            $this->tokens->invalidateOpenTokensOf($user->id(), $now);
            $this->tokens->save(PasswordResetToken::issue($user->id(), $plain, $now));
            $this->audit->record($actor, 'auth.password_reset_requested', 'user', $user->id());
        });

        $separator = str_contains($this->resetUrl, '?') ? '&' : '?';
        $this->mailer->sendPasswordReset(
            $user->email()->value(),
            $user->name(),
            $this->resetUrl . $separator . 'token=' . $plain,
        );
    }
}
