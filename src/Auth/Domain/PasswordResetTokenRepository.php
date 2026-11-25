<?php

declare(strict_types=1);

namespace Goomer\Auth\Domain;

interface PasswordResetTokenRepository
{
    /** Grava um token novo ou o uso de um existente (pelo hash). */
    public function save(PasswordResetToken $token): void;

    public function findByHash(string $tokenHash): ?PasswordResetToken;

    /** Um novo pedido invalida os anteriores ainda não usados. */
    public function invalidateOpenTokensOf(int $userId, \DateTimeImmutable $now): void;
}
