<?php

declare(strict_types=1);

namespace Goomer\Shared\Application\Audit;

/**
 * Quem fez a ação: usuário (id e e-mail no momento da ação), restaurante dele e IP da requisição.
 * Montado na borda (token + requisição); o domínio não sabe de HTTP.
 */
final readonly class Actor
{
    public function __construct(
        public int $userId,
        public string $email,
        public int $restaurantId,
        public ?string $ip = null,
    ) {}
}
