<?php

declare(strict_types=1);

namespace Goomer\Product\Http;

use Goomer\Auth\Http\CurrentActor;
use Goomer\Auth\Http\JwtMiddleware;
use Goomer\Product\Application\ChangeProductVisibilityUseCase;
use Goomer\Product\Application\CreateProductUseCase;
use Goomer\Product\Application\DeleteProductUseCase;
use Goomer\Product\Application\GetProductUseCase;
use Goomer\Product\Application\ListProductsUseCase;
use Goomer\Product\Application\ProductData;
use Goomer\Product\Application\ProductOutput;
use Goomer\Product\Application\ReorderProductsUseCase;
use Goomer\Product\Application\UpdateProductUseCase;
use Goomer\Shared\Http\Input;
use Goomer\Shared\Http\Json;
use OpenApi\Attributes as OA;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

final readonly class ProductController
{
    public function __construct(
        private ListProductsUseCase $list,
        private CreateProductUseCase $create,
        private GetProductUseCase $get,
        private UpdateProductUseCase $update,
        private ChangeProductVisibilityUseCase $visibility,
        private ReorderProductsUseCase $reorder,
        private DeleteProductUseCase $delete,
        private CurrentActor $actor,
    ) {}

    /** @param array<string, string> $args */
    #[OA\Get(path: '/api/v1/products', summary: 'Lista os produtos do restaurante', security: [['bearerAuth' => []]], tags: ['Produtos'])]
    #[OA\Parameter(name: 'category', in: 'query', schema: new OA\Schema(type: 'string', enum: ['entradas', 'pratos_principais', 'sobremesas', 'bebidas']))]
    #[OA\Parameter(name: 'visible', in: 'query', schema: new OA\Schema(type: 'boolean'))]
    #[OA\Parameter(name: 'page', in: 'query', schema: new OA\Schema(type: 'integer', default: 1))]
    #[OA\Parameter(name: 'perPage', in: 'query', schema: new OA\Schema(type: 'integer', default: 20, maximum: 100))]
    #[OA\Response(response: 200, description: 'Página de produtos')]
    #[OA\Response(response: 401, description: 'Sem token ou token inválido')]
    public function index(Request $request, Response $response, array $args): Response
    {
        $page = $this->list->execute(
            JwtMiddleware::context($request)->restaurantId,
            Json::query($request, 'category'),
            Json::queryBool($request, 'visible'),
            Json::queryInt($request, 'page', 1) ?? 1,
            Json::queryInt($request, 'perPage', 20) ?? 20,
        );

        return Json::page(
            $response,
            array_map(static fn(ProductOutput $p): array => $p->toArray(), $page->items),
            $page->page,
            $page->perPage,
            $page->total,
        );
    }

    /** @param array<string, string> $args */
    #[OA\Post(path: '/api/v1/products', summary: 'Cria produto', security: [['bearerAuth' => []]], tags: ['Produtos'])]
    #[OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/ProductInput'))]
    #[OA\Response(response: 201, description: 'Criado, com header Location')]
    #[OA\Response(response: 422, description: 'Dados inválidos')]
    public function store(Request $request, Response $response, array $args): Response
    {
        $output = $this->create->execute($this->actor->of($request), $this->data($request));

        return Json::respond($response, $output->toArray(), 201)
            ->withHeader('Location', '/api/v1/products/' . $output->id);
    }

    /** @param array<string, string> $args */
    #[OA\Get(path: '/api/v1/products/{id}', summary: 'Detalha um produto', security: [['bearerAuth' => []]], tags: ['Produtos'])]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\Response(response: 200, description: 'Produto')]
    #[OA\Response(response: 404, description: 'Não encontrado (ou de outro restaurante)')]
    public function show(Request $request, Response $response, array $args): Response
    {
        $output = $this->get->execute(JwtMiddleware::context($request)->restaurantId, Json::id($args));

        return Json::respond($response, $output->toArray());
    }

    /** @param array<string, string> $args */
    #[OA\Put(path: '/api/v1/products/{id}', summary: 'Atualiza o produto inteiro', security: [['bearerAuth' => []]], tags: ['Produtos'])]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/ProductInput'))]
    #[OA\Response(response: 200, description: 'Produto atualizado')]
    public function update(Request $request, Response $response, array $args): Response
    {
        $id = Json::id($args);
        $output = $this->update->execute($this->actor->of($request), $id, $this->data($request));

        return Json::respond($response, $output->toArray());
    }

    /** @param array<string, string> $args */
    #[OA\Patch(path: '/api/v1/products/{id}/visibility', summary: 'Mostra ou esconde o produto', security: [['bearerAuth' => []]], tags: ['Produtos'])]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\RequestBody(required: true, content: new OA\JsonContent(properties: [new OA\Property(property: 'visible', type: 'boolean')]))]
    #[OA\Response(response: 200, description: 'Produto com a nova visibilidade')]
    public function visibility(Request $request, Response $response, array $args): Response
    {
        $id = Json::id($args);
        $input = Input::fromRequest($request);
        $visible = $input->bool('visible');
        $input->validate();

        $output = $this->visibility->execute($this->actor->of($request), $id, $visible);

        return Json::respond($response, $output->toArray());
    }

    /** @param array<string, string> $args */
    #[OA\Put(path: '/api/v1/products/order', summary: 'Define a ordem dos produtos no cardápio', security: [['bearerAuth' => []]], tags: ['Produtos'])]
    #[OA\RequestBody(required: true, content: new OA\JsonContent(properties: [new OA\Property(property: 'ids', type: 'array', items: new OA\Items(type: 'integer'))]))]
    #[OA\Response(response: 204, description: 'Ordem gravada')]
    public function order(Request $request, Response $response, array $args): Response
    {
        $input = Input::fromRequest($request);
        $ids = $input->intList('ids');
        $input->validate();

        $this->reorder->execute($this->actor->of($request), $ids);

        return Json::noContent($response);
    }

    /** @param array<string, string> $args */
    #[OA\Delete(path: '/api/v1/products/{id}', summary: 'Exclui o produto e as promoções dele', security: [['bearerAuth' => []]], tags: ['Produtos'])]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\Response(response: 204, description: 'Excluído')]
    public function destroy(Request $request, Response $response, array $args): Response
    {
        $this->delete->execute($this->actor->of($request), Json::id($args));

        return Json::noContent($response);
    }

    private function data(Request $request): ProductData
    {
        $input = Input::fromRequest($request);
        $data = new ProductData(
            $input->string('name'),
            $input->int('price'),
            $input->string('category'),
            $input->bool('visible', true),
            $input->windows('availability', required: false),
        );
        $input->validate();

        return $data;
    }
}
