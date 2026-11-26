<?php

declare(strict_types=1);

namespace Goomer\User\Http;

use Goomer\Auth\Http\CurrentActor;
use Goomer\Auth\Http\JwtMiddleware;
use Goomer\Shared\Http\Input;
use Goomer\Shared\Http\Json;
use Goomer\User\Application\CreateUserUseCase;
use Goomer\User\Application\DeleteUserUseCase;
use Goomer\User\Application\ListUserAuditLogsUseCase;
use Goomer\User\Application\ListUsersUseCase;
use Goomer\User\Application\UserOutput;
use OpenApi\Attributes as OA;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

final readonly class UserController
{
    public function __construct(
        private ListUsersUseCase $list,
        private CreateUserUseCase $create,
        private DeleteUserUseCase $delete,
        private ListUserAuditLogsUseCase $auditLogs,
        private CurrentActor $actor,
    ) {}

    /** @param array<string, string> $args */
    #[OA\Get(path: '/api/v1/users', summary: 'Lista os usuários do restaurante (sem hash de senha)', security: [['bearerAuth' => []]], tags: ['Usuários'])]
    #[OA\Response(response: 200, description: 'Usuários')]
    public function index(Request $request, Response $response, array $args): Response
    {
        $users = $this->list->execute(JwtMiddleware::context($request)->restaurantId);

        return Json::respond($response, ['data' => array_map(static fn(UserOutput $u): array => $u->toArray(), $users)]);
    }

    /** @param array<string, string> $args */
    #[OA\Post(path: '/api/v1/users', summary: 'Cadastra usuário no restaurante do token', security: [['bearerAuth' => []]], tags: ['Usuários'])]
    #[OA\RequestBody(required: true, content: new OA\JsonContent(properties: [
        new OA\Property(property: 'name', type: 'string'),
        new OA\Property(property: 'email', type: 'string'),
        new OA\Property(property: 'password', type: 'string', minLength: 8),
    ]))]
    #[OA\Response(response: 201, description: 'Criado')]
    #[OA\Response(response: 409, description: 'E-mail já cadastrado')]
    public function store(Request $request, Response $response, array $args): Response
    {
        $input = Input::fromRequest($request);
        $name = $input->string('name');
        $email = $input->string('email');
        $password = $input->string('password');
        $input->validate();

        $output = $this->create->execute($this->actor->of($request), $name, $email, $password);

        return Json::respond($response, $output->toArray(), 201)->withHeader('Location', '/api/v1/users/' . $output->id);
    }

    /** @param array<string, string> $args */
    #[OA\Delete(path: '/api/v1/users/{id}', summary: 'Remove um usuário do restaurante', security: [['bearerAuth' => []]], tags: ['Usuários'])]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\Response(response: 204, description: 'Removido')]
    #[OA\Response(response: 422, description: 'Tentou remover a si mesmo')]
    public function destroy(Request $request, Response $response, array $args): Response
    {
        $this->delete->execute($this->actor->of($request), Json::id($args));

        return Json::noContent($response);
    }

    /** @param array<string, string> $args */
    #[OA\Get(
        path: '/api/v1/users/{id}/audit-logs',
        description: 'Ações de escrita feitas pelo usuário {id}, mais recentes primeiro. Os dias são do fuso do restaurante e inclusivos; sem dateEnd, vai até hoje. Usuário já excluído continua tendo log.',
        summary: 'Log de auditoria de um usuário',
        security: [['bearerAuth' => []]],
        tags: ['Usuários'],
    )]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\Parameter(name: 'dateIni', in: 'query', required: true, schema: new OA\Schema(type: 'string', format: 'date', example: '2026-11-25'))]
    #[OA\Parameter(name: 'dateEnd', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'date', example: '2026-11-26'))]
    #[OA\Parameter(name: 'page', in: 'query', schema: new OA\Schema(type: 'integer', default: 1))]
    #[OA\Parameter(name: 'perPage', in: 'query', schema: new OA\Schema(type: 'integer', default: 50, maximum: 100))]
    #[OA\Response(response: 200, description: 'data (id, action, entityType, entityId, changes, ip, createdAt), user {id, email} e meta {page, perPage, total}')]
    #[OA\Response(response: 401, description: 'Sem token ou token inválido')]
    #[OA\Response(response: 404, description: 'Usuário inexistente ou de outro restaurante')]
    #[OA\Response(response: 422, description: 'Data inválida ou dateEnd anterior a dateIni')]
    public function auditLogs(Request $request, Response $response, array $args): Response
    {
        $page = $this->auditLogs->execute(
            JwtMiddleware::context($request)->restaurantId,
            Json::id($args),
            Json::query($request, 'dateIni'),
            Json::query($request, 'dateEnd'),
            Json::queryInt($request, 'page', 1) ?? 1,
            Json::queryInt($request, 'perPage', 50) ?? 50,
        );

        return Json::respond($response, $page->toArray());
    }
}
