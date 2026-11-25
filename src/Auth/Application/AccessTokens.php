<?php

declare(strict_types=1);

namespace Goomer\Auth\Application;

use Goomer\Shared\Application\Exception\Unauthenticated;

/**
 * Emissão e verificação do token de acesso (JWT HS256 na infraestrutura).
 */
interface AccessTokens
{
    public const int TTL_SECONDS = 3600;

    public function issue(int $userId, int $restaurantId): string;

    /**
     * @throws Unauthenticated se o token for inválido ou estiver vencido
     */
    public function verify(string $token): AuthContext;
}
