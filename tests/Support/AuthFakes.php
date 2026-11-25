<?php

declare(strict_types=1);

namespace Goomer\Tests\Support;

use Goomer\Auth\Application\AccessTokens;
use Goomer\Auth\Application\AuthContext;
use Goomer\Auth\Application\Mailer;
use Goomer\Auth\Application\ResetRequestThrottle;
use Goomer\Auth\Domain\PasswordResetToken;
use Goomer\Auth\Domain\PasswordResetTokenRepository;
use Goomer\Shared\Application\Exception\Unauthenticated;

/**
 * Dublês das portas de autenticação, todos em memória e determinísticos.
 */
final class AuthFakes implements AccessTokens, Mailer, ResetRequestThrottle, PasswordResetTokenRepository
{
    /** @var list<array{to: string, name: string, link: string}> */
    public array $sent = [];

    /** @var list<array{email: string, ip: string, at: \DateTimeImmutable}> */
    public array $requests = [];

    /** @var array<string, PasswordResetToken> */
    public array $tokens = [];

    public function issue(int $userId, int $restaurantId): string
    {
        return "token-{$userId}-{$restaurantId}";
    }

    public function verify(string $token): AuthContext
    {
        if (preg_match('/^token-(\d+)-(\d+)$/', $token, $m) !== 1) {
            throw new Unauthenticated('Token inválido.');
        }

        return new AuthContext((int) $m[1], (int) $m[2]);
    }

    public function sendPasswordReset(string $toEmail, string $toName, string $resetLink): void
    {
        $this->sent[] = ['to' => $toEmail, 'name' => $toName, 'link' => $resetLink];
    }

    public function countByEmailSince(string $email, \DateTimeImmutable $since): int
    {
        return count(array_filter($this->requests, static fn(array $r): bool => $r['email'] === $email && $r['at'] > $since));
    }

    public function countByIpSince(string $ip, \DateTimeImmutable $since): int
    {
        return count(array_filter($this->requests, static fn(array $r): bool => $r['ip'] === $ip && $r['at'] > $since));
    }

    public function record(string $email, string $ip, \DateTimeImmutable $at): void
    {
        $this->requests[] = ['email' => $email, 'ip' => $ip, 'at' => $at];
    }

    public function save(PasswordResetToken $token): void
    {
        $this->tokens[$token->tokenHash()] = clone $token;
    }

    public function findByHash(string $tokenHash): ?PasswordResetToken
    {
        return isset($this->tokens[$tokenHash]) ? clone $this->tokens[$tokenHash] : null;
    }

    public function invalidateOpenTokensOf(int $userId, \DateTimeImmutable $now): void
    {
        foreach ($this->tokens as $hash => $token) {
            if ($token->userId() === $userId && $token->usedAt() === null) {
                $this->tokens[$hash] = PasswordResetToken::restore($hash, $userId, $token->expiresAt(), $now);
            }
        }
    }

    /** Último token em texto puro enviado por e-mail. */
    public function lastToken(): string
    {
        $last = end($this->sent);
        if ($last === false) {
            throw new \LogicException('Nenhum e-mail enviado.');
        }
        parse_str((string) parse_url($last['link'], PHP_URL_QUERY), $query);
        $token = $query['token'] ?? null;

        return is_string($token) ? $token : throw new \LogicException('Link sem token.');
    }
}
