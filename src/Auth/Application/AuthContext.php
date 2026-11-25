<?php

declare(strict_types=1);

namespace Goomer\Auth\Application;

/**
 * Quem está chamando: o restaurante das rotas protegidas vem sempre daqui, nunca da URL.
 */
final readonly class AuthContext
{
    public function __construct(
        public int $userId,
        public int $restaurantId,
    ) {}
}
