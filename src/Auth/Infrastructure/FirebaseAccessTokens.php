<?php

declare(strict_types=1);

namespace Goomer\Auth\Infrastructure;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Goomer\Auth\Application\AccessTokens;
use Goomer\Auth\Application\AuthContext;
use Goomer\Shared\Application\Exception\Unauthenticated;
use Goomer\Shared\Domain\Clock;

/**
 * JWT HS256 com validade de 1 hora: `sub` é o id do usuário e `rid` o do restaurante.
 */
final readonly class FirebaseAccessTokens implements AccessTokens
{
    private const string ALGORITHM = 'HS256';

    public function __construct(
        private string $secret,
        private Clock $clock,
    ) {
        if (strlen($secret) < 32) {
            throw new \InvalidArgumentException('JWT_SECRET precisa ter pelo menos 32 caracteres.');
        }
    }

    public function issue(int $userId, int $restaurantId): string
    {
        $now = $this->clock->now()->getTimestamp();

        return JWT::encode([
            'sub' => (string) $userId,
            'rid' => $restaurantId,
            'iat' => $now,
            'exp' => $now + self::TTL_SECONDS,
        ], $this->secret, self::ALGORITHM);
    }

    public function verify(string $token): AuthContext
    {
        JWT::$timestamp = $this->clock->now()->getTimestamp();
        try {
            $claims = (array) JWT::decode($token, new Key($this->secret, self::ALGORITHM));
        } catch (\Throwable) {
            throw new Unauthenticated('Token inválido ou vencido.');
        } finally {
            JWT::$timestamp = null;
        }

        $userId = $claims['sub'] ?? null;
        $restaurantId = $claims['rid'] ?? null;
        if (!is_numeric($userId) || !is_int($restaurantId)) {
            throw new Unauthenticated('Token sem usuário ou restaurante.');
        }

        return new AuthContext((int) $userId, $restaurantId);
    }
}
