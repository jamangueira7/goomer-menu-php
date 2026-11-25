<?php

declare(strict_types=1);

namespace Goomer\Menu\Http;

use Goomer\Menu\Application\GetMenuUseCase;
use Goomer\Shared\Http\Json;
use OpenApi\Attributes as OA;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

final readonly class MenuController
{
    public function __construct(private GetMenuUseCase $menu) {}

    /** @param array<string, string> $args */
    #[OA\Get(
        path: '/api/v1/restaurants/{id}/menu',
        summary: 'Cardápio consolidado (público)',
        description: 'Só produtos visíveis e disponíveis agora no fuso do restaurante, agrupados por categoria, com a promoção ativa.',
        tags: ['Cardápio'],
    )]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\Response(response: 200, description: 'Cardápio')]
    #[OA\Response(response: 404, description: 'Restaurante não encontrado')]
    public function show(Request $request, Response $response, array $args): Response
    {
        return Json::respond($response, $this->menu->execute(Json::id($args)));
    }
}
