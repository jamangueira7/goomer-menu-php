<?php

declare(strict_types=1);

namespace Goomer\Auth\Http;

use Goomer\Auth\Application\AccessTokens;
use Goomer\Auth\Application\AuthContext;
use Goomer\Shared\Application\Exception\Unauthenticated;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Único middleware de autenticação, aplicado ao grupo de rotas protegidas.
 */
final readonly class JwtMiddleware implements MiddlewareInterface
{
    public const string ATTRIBUTE = 'auth';

    public function __construct(private AccessTokens $tokens) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $header = $request->getHeaderLine('Authorization');
        if (preg_match('/^Bearer\s+(\S+)$/i', $header, $m) !== 1) {
            throw new Unauthenticated('Envie o header Authorization: Bearer <token>.');
        }

        return $handler->handle($request->withAttribute(self::ATTRIBUTE, $this->tokens->verify($m[1])));
    }

    public static function context(ServerRequestInterface $request): AuthContext
    {
        $auth = $request->getAttribute(self::ATTRIBUTE);
        if (!$auth instanceof AuthContext) {
            throw new Unauthenticated('Requisição sem autenticação.');
        }

        return $auth;
    }
}
