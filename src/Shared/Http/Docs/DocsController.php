<?php

declare(strict_types=1);

namespace Goomer\Shared\Http\Docs;

use OpenApi\Generator;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Serve o OpenAPI gerado dos atributos do código e um Swagger UI em /docs (só em APP_ENV=dev).
 */
final readonly class DocsController
{
    public function __construct(private string $sourceDir) {}

    /** @param array<string, string> $args */
    public function ui(Request $request, Response $response, array $args): Response
    {
        $response->getBody()->write(<<<'HTML'
            <!doctype html>
            <html lang="pt-BR">
            <head>
              <meta charset="utf-8">
              <title>Goomer Menu API (PHP)</title>
              <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swagger-ui-dist@5.17.14/swagger-ui.css">
            </head>
            <body>
              <div id="swagger-ui"></div>
              <script src="https://cdn.jsdelivr.net/npm/swagger-ui-dist@5.17.14/swagger-ui-bundle.js"></script>
              <script>SwaggerUIBundle({ url: '/docs/openapi.json', dom_id: '#swagger-ui' });</script>
            </body>
            </html>
            HTML);

        return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
    }

    /** @param array<string, string> $args */
    public function spec(Request $request, Response $response, array $args): Response
    {
        $openapi = (new Generator())->generate([$this->sourceDir]);
        $response->getBody()->write($openapi?->toJson() ?? '{}');

        return $response->withHeader('Content-Type', 'application/json');
    }
}
