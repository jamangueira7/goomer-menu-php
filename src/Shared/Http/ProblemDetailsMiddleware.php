<?php

declare(strict_types=1);

namespace Goomer\Shared\Http;

use Goomer\Shared\Application\Exception\TooManyRequests;
use Goomer\Shared\Application\Exception\Unauthenticated;
use Goomer\Shared\Domain\Exception\Conflict;
use Goomer\Shared\Domain\Exception\NotFound;
use Goomer\Shared\Domain\Exception\ValidationError;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Exception\HttpException;

/**
 * Traduz exceções em Problem Details (RFC 9457, `application/problem+json`).
 */
final readonly class ProblemDetailsMiddleware implements MiddlewareInterface
{
    public function __construct(private ResponseFactoryInterface $responses) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        try {
            return $handler->handle($request);
        } catch (\Throwable $e) {
            return $this->toProblem($e, $request);
        }
    }

    private function toProblem(\Throwable $e, ServerRequestInterface $request): ResponseInterface
    {
        $extra = [];
        $headers = [];
        [$status, $detail] = match (true) {
            $e instanceof InvalidInput && $e->isMalformed() => [400, $e->getMessage()],
            $e instanceof ValidationError => [422, $e->getMessage()],
            $e instanceof NotFound => [404, $e->getMessage()],
            $e instanceof Conflict => [409, $e->getMessage()],
            $e instanceof Unauthenticated => [401, $e->getMessage()],
            $e instanceof TooManyRequests => [429, $e->getMessage()],
            $e instanceof HttpException => [$e->getCode(), $e->getMessage()],
            default => [500, 'Erro interno. Tente de novo mais tarde.'],
        };

        if ($e instanceof InvalidInput && !$e->isMalformed()) {
            $extra['errors'] = $e->errors();
        } elseif ($e instanceof ValidationError && $e->field() !== null) {
            $extra['errors'] = [$e->field() => $e->getMessage()];
        }
        if ($e instanceof Unauthenticated) {
            $headers['WWW-Authenticate'] = 'Bearer';
        }
        if ($e instanceof TooManyRequests) {
            $headers['Retry-After'] = (string) $e->retryAfterSeconds();
        }
        if ($status === 500) {
            error_log(sprintf('[api] %s: %s em %s:%d', $e::class, $e->getMessage(), $e->getFile(), $e->getLine()));
        }

        $response = $this->responses->createResponse($status);
        $body = [
            'type' => 'about:blank',
            'title' => $response->getReasonPhrase(),
            'status' => $status,
            'detail' => $detail,
            'instance' => $request->getUri()->getPath(),
            ...$extra,
        ];
        $response->getBody()->write(json_encode($body, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        foreach ($headers as $name => $value) {
            $response = $response->withHeader($name, $value);
        }

        return $response->withHeader('Content-Type', 'application/problem+json');
    }
}
