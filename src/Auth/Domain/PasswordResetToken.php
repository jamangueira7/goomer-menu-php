<?php

declare(strict_types=1);

namespace Goomer\Auth\Domain;

/**
 * Pedido de redefinição de senha. Guarda só o hash SHA-256 do token; vale 30 minutos e uma única vez.
 */
final class PasswordResetToken
{
    public const int TTL_MINUTES = 30;

    private function __construct(
        private readonly string $tokenHash,
        private readonly int $userId,
        private readonly \DateTimeImmutable $expiresAt,
        private ?\DateTimeImmutable $usedAt,
    ) {}

    public static function issue(int $userId, string $plainToken, \DateTimeImmutable $now): self
    {
        return new self(
            self::hash($plainToken),
            $userId,
            $now->modify(sprintf('+%d minutes', self::TTL_MINUTES)),
            null,
        );
    }

    public static function restore(string $tokenHash, int $userId, \DateTimeImmutable $expiresAt, ?\DateTimeImmutable $usedAt): self
    {
        return new self($tokenHash, $userId, $expiresAt, $usedAt);
    }

    public static function hash(string $plainToken): string
    {
        return hash('sha256', $plainToken);
    }

    public function isUsableAt(\DateTimeImmutable $now): bool
    {
        return $this->usedAt === null && $now < $this->expiresAt;
    }

    public function consume(\DateTimeImmutable $now): void
    {
        if (!$this->isUsableAt($now)) {
            throw new InvalidResetToken();
        }
        $this->usedAt = $now;
    }

    public function tokenHash(): string
    {
        return $this->tokenHash;
    }

    public function userId(): int
    {
        return $this->userId;
    }

    public function expiresAt(): \DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function usedAt(): ?\DateTimeImmutable
    {
        return $this->usedAt;
    }
}
