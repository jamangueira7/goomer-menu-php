<?php

declare(strict_types=1);

namespace Goomer\Auth\Http;

use Goomer\Shared\Application\Audit\Actor;
use Goomer\Shared\Application\Exception\Unauthenticated;
use Goomer\User\Domain\UserRepository;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Monta o `Actor` das escritas: usuário e restaurante do token, e-mail atual do usuário e IP da requisição.
 */
final readonly class CurrentActor
{
    public function __construct(private UserRepository $users) {}

    public function of(ServerRequestInterface $request): Actor
    {
        $auth = JwtMiddleware::context($request);
        $user = $this->users->find($auth->restaurantId, $auth->userId)
            ?? throw new Unauthenticated('O usuário do token não existe mais.');

        return new Actor($user->id(), $user->email()->value(), $user->restaurantId(), self::ip($request));
    }

    /** IP do cliente (REMOTE_ADDR), ou null se não for um endereço válido. */
    public static function ip(ServerRequestInterface $request): ?string
    {
        $ip = $request->getServerParams()['REMOTE_ADDR'] ?? null;

        return is_string($ip) && filter_var($ip, FILTER_VALIDATE_IP) !== false ? $ip : null;
    }
}
