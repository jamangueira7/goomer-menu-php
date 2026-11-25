<?php

declare(strict_types=1);

namespace Goomer\Shared\Http;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Respostas JSON e leitura de parâmetros de rota e de query.
 */
final class Json
{
    public static function respond(ResponseInterface $response, mixed $data, int $status = 200): ResponseInterface
    {
        $response->getBody()->write(json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        return $response->withHeader('Content-Type', 'application/json')->withStatus($status);
    }

    public static function noContent(ResponseInterface $response): ResponseInterface
    {
        return $response->withStatus(204);
    }

    /**
     * @param list<array<string, mixed>> $data itens da página já convertidos
     */
    public static function page(ResponseInterface $response, array $data, int $page, int $perPage, int $total): ResponseInterface
    {
        return self::respond($response, [
            'data' => $data,
            'meta' => ['page' => $page, 'perPage' => $perPage, 'total' => $total],
        ]);
    }

    /**
     * Id numérico de rota; qualquer outra coisa vira 404 (o recurso não existe).
     *
     * @param array<string, string> $args
     */
    public static function id(array $args, string $name = 'id'): int
    {
        $value = $args[$name] ?? '';
        if (preg_match('/^[1-9]\d{0,17}$/', $value) !== 1) {
            throw new RouteNotFound(sprintf('Recurso "%s" não encontrado.', $value));
        }

        return (int) $value;
    }

    public static function query(ServerRequestInterface $request, string $name): ?string
    {
        $value = $request->getQueryParams()[$name] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }

    public static function queryInt(ServerRequestInterface $request, string $name, ?int $default): ?int
    {
        $value = self::query($request, $name);
        if ($value === null) {
            return $default;
        }
        if (preg_match('/^-?\d{1,9}$/', $value) !== 1) {
            throw new InvalidInput([$name => 'Precisa ser um número inteiro.']);
        }

        return (int) $value;
    }

    public static function queryBool(ServerRequestInterface $request, string $name): ?bool
    {
        return match (self::query($request, $name)) {
            null => null,
            'true', '1' => true,
            'false', '0' => false,
            default => throw new InvalidInput([$name => 'Use true ou false.']),
        };
    }
}
