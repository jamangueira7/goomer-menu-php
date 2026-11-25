<?php

declare(strict_types=1);

namespace Goomer\Auth\Infrastructure;

use Goomer\Auth\Domain\PasswordResetToken;
use Goomer\Auth\Domain\PasswordResetTokenRepository;
use Goomer\Shared\Infrastructure\Database\Database;

final readonly class PdoPasswordResetTokenRepository implements PasswordResetTokenRepository
{
    public function __construct(private Database $db) {}

    public function save(PasswordResetToken $token): void
    {
        $this->db->execute(
            <<<'SQL'
                INSERT INTO password_reset_tokens (user_id, token_hash, expires_at, used_at)
                VALUES (:user_id, :token_hash, CAST(:expires_at AS timestamptz), CAST(:used_at AS timestamptz))
                ON CONFLICT (token_hash) DO UPDATE SET used_at = EXCLUDED.used_at
                SQL,
            [
                'user_id' => $token->userId(),
                'token_hash' => $token->tokenHash(),
                'expires_at' => $token->expiresAt()->format(\DATE_ATOM),
                'used_at' => $token->usedAt()?->format(\DATE_ATOM),
            ],
        );
    }

    public function findByHash(string $tokenHash): ?PasswordResetToken
    {
        $row = $this->db->fetchOne(
            'SELECT token_hash, user_id, expires_at, used_at FROM password_reset_tokens WHERE token_hash = :hash',
            ['hash' => $tokenHash],
        );

        return $row === null ? null : PasswordResetToken::restore(
            $row->string('token_hash'),
            $row->int('user_id'),
            $row->dateTime('expires_at'),
            $row->nullableDateTime('used_at'),
        );
    }

    public function invalidateOpenTokensOf(int $userId, \DateTimeImmutable $now): void
    {
        $this->db->execute(
            'UPDATE password_reset_tokens SET used_at = CAST(:now AS timestamptz) WHERE user_id = :user_id AND used_at IS NULL',
            ['now' => $now->format(\DATE_ATOM), 'user_id' => $userId],
        );
    }
}
