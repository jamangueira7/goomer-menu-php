<?php

declare(strict_types=1);

namespace Goomer\Promotion\Http;

use Goomer\Auth\Http\CurrentActor;
use Goomer\Auth\Http\JwtMiddleware;
use Goomer\Promotion\Application\CreatePromotionUseCase;
use Goomer\Promotion\Application\DeletePromotionUseCase;
use Goomer\Promotion\Application\GetPromotionUseCase;
use Goomer\Promotion\Application\ListPromotionsUseCase;
use Goomer\Promotion\Application\PromotionData;
use Goomer\Promotion\Application\PromotionOutput;
use Goomer\Promotion\Application\UpdatePromotionUseCase;
use Goomer\Shared\Http\Input;
use Goomer\Shared\Http\Json;
use OpenApi\Attributes as OA;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

final readonly class PromotionController
{
    public function __construct(
        private ListPromotionsUseCase $list,
        private CreatePromotionUseCase $create,
        private GetPromotionUseCase $get,
        private UpdatePromotionUseCase $update,
        private DeletePromotionUseCase $delete,
        private CurrentActor $actor,
    ) {}

    /** @param array<string, string> $args */
    #[OA\Get(path: '/api/v1/promotions', summary: 'Lista as promoções', security: [['bearerAuth' => []]], tags: ['Promoções'])]
    #[OA\Parameter(name: 'productId', in: 'query', schema: new OA\Schema(type: 'integer'))]
    #[OA\Response(response: 200, description: 'Promoções do restaurante')]
    public function index(Request $request, Response $response, array $args): Response
    {
        $items = $this->list->execute(
            JwtMiddleware::context($request)->restaurantId,
            Json::queryInt($request, 'productId', null),
        );

        return Json::respond($response, ['data' => array_map(static fn(PromotionOutput $p): array => $p->toArray(), $items)]);
    }

    /** @param array<string, string> $args */
    #[OA\Post(path: '/api/v1/promotions', summary: 'Cria promoção para um produto', security: [['bearerAuth' => []]], tags: ['Promoções'])]
    #[OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/PromotionInput'))]
    #[OA\Response(response: 201, description: 'Criada, com header Location')]
    #[OA\Response(response: 409, description: 'Janela sobreposta a outra promoção do produto')]
    #[OA\Response(response: 422, description: 'Dados inválidos ou preço promocional >= preço normal')]
    public function store(Request $request, Response $response, array $args): Response
    {
        $input = Input::fromRequest($request);
        $productId = $input->int('productId');
        $data = $this->data($input);

        $output = $this->create->execute($this->actor->of($request), $productId, $data);

        return Json::respond($response, $output->toArray(), 201)
            ->withHeader('Location', '/api/v1/promotions/' . $output->id);
    }

    /** @param array<string, string> $args */
    #[OA\Get(path: '/api/v1/promotions/{id}', summary: 'Detalha uma promoção', security: [['bearerAuth' => []]], tags: ['Promoções'])]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\Response(response: 200, description: 'Promoção')]
    #[OA\Response(response: 404, description: 'Não encontrada')]
    public function show(Request $request, Response $response, array $args): Response
    {
        $output = $this->get->execute(JwtMiddleware::context($request)->restaurantId, Json::id($args));

        return Json::respond($response, $output->toArray());
    }

    /** @param array<string, string> $args */
    #[OA\Put(path: '/api/v1/promotions/{id}', summary: 'Atualiza descrição, preço e janelas', security: [['bearerAuth' => []]], tags: ['Promoções'])]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/PromotionInput'))]
    #[OA\Response(response: 200, description: 'Promoção atualizada')]
    public function update(Request $request, Response $response, array $args): Response
    {
        $id = Json::id($args);
        $data = $this->data(Input::fromRequest($request));
        $output = $this->update->execute($this->actor->of($request), $id, $data);

        return Json::respond($response, $output->toArray());
    }

    /** @param array<string, string> $args */
    #[OA\Delete(path: '/api/v1/promotions/{id}', summary: 'Exclui a promoção', security: [['bearerAuth' => []]], tags: ['Promoções'])]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\Response(response: 204, description: 'Excluída')]
    public function destroy(Request $request, Response $response, array $args): Response
    {
        $this->delete->execute($this->actor->of($request), Json::id($args));

        return Json::noContent($response);
    }

    private function data(Input $input): PromotionData
    {
        $data = new PromotionData(
            $input->string('description'),
            $input->int('price'),
            $input->windows('windows', required: true),
        );
        $input->validate();

        return $data;
    }
}
