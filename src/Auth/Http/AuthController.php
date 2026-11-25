<?php

declare(strict_types=1);

namespace Goomer\Auth\Http;

use Goomer\Auth\Application\ForgotPasswordUseCase;
use Goomer\Auth\Application\LoginUseCase;
use Goomer\Auth\Application\ResetPasswordUseCase;
use Goomer\Shared\Http\Input;
use Goomer\Shared\Http\Json;
use OpenApi\Attributes as OA;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Rotas sem token: servem justamente a quem ainda não tem um.
 */
final readonly class AuthController
{
    public function __construct(
        private LoginUseCase $login,
        private ForgotPasswordUseCase $forgot,
        private ResetPasswordUseCase $reset,
    ) {}

    /** @param array<string, string> $args */
    #[OA\Post(path: '/api/v1/auth/login', summary: 'Troca e-mail e senha por um JWT (1 hora)', tags: ['Autenticação'])]
    #[OA\RequestBody(required: true, content: new OA\JsonContent(properties: [
        new OA\Property(property: 'email', type: 'string', example: 'admin@bardojoao.com'),
        new OA\Property(property: 'password', type: 'string', example: 'goomer123'),
    ]))]
    #[OA\Response(response: 200, description: 'accessToken, tokenType e expiresIn')]
    #[OA\Response(response: 401, description: 'E-mail ou senha inválidos')]
    public function login(Request $request, Response $response, array $args): Response
    {
        $input = Input::fromRequest($request);
        $email = $input->string('email');
        $password = $input->string('password');
        $input->validate();

        return Json::respond($response, $this->login->execute($email, $password));
    }

    /** @param array<string, string> $args */
    #[OA\Post(path: '/api/v1/auth/forgot-password', summary: 'Envia o link de redefinição por e-mail', tags: ['Autenticação'])]
    #[OA\RequestBody(required: true, content: new OA\JsonContent(properties: [new OA\Property(property: 'email', type: 'string')]))]
    #[OA\Response(response: 202, description: 'Sempre 202, exista ou não o e-mail')]
    #[OA\Response(response: 429, description: 'Mais de 5 pedidos por e-mail ou IP em 15 minutos')]
    public function forgotPassword(Request $request, Response $response, array $args): Response
    {
        $input = Input::fromRequest($request);
        $email = $input->string('email');
        $input->validate();

        $ip = $request->getServerParams()['REMOTE_ADDR'] ?? 'desconhecido';
        $this->forgot->execute($email, is_string($ip) ? $ip : 'desconhecido');

        return Json::respond($response, [
            'message' => 'Se o e-mail estiver cadastrado, você vai receber um link para redefinir a senha.',
        ], 202);
    }

    /** @param array<string, string> $args */
    #[OA\Post(path: '/api/v1/auth/reset-password', summary: 'Define a nova senha com o token do e-mail', tags: ['Autenticação'])]
    #[OA\RequestBody(required: true, content: new OA\JsonContent(properties: [
        new OA\Property(property: 'token', type: 'string'),
        new OA\Property(property: 'password', type: 'string', minLength: 8),
    ]))]
    #[OA\Response(response: 204, description: 'Senha trocada')]
    #[OA\Response(response: 422, description: 'Token inválido, usado ou vencido, ou senha curta')]
    public function resetPassword(Request $request, Response $response, array $args): Response
    {
        $input = Input::fromRequest($request);
        $token = $input->string('token');
        $password = $input->string('password');
        $input->validate();

        $this->reset->execute($token, $password);

        return Json::noContent($response);
    }
}
