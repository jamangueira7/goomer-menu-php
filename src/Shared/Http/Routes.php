<?php

declare(strict_types=1);

namespace Goomer\Shared\Http;

use Goomer\Auth\Http\AuthController;
use Goomer\Auth\Http\JwtMiddleware;
use Goomer\Menu\Http\MenuController;
use Goomer\Product\Http\ProductController;
use Goomer\Promotion\Http\PromotionController;
use Goomer\Shared\Http\Docs\DocsController;
use Goomer\User\Http\UserController;
use Slim\App;
use Slim\Routing\RouteCollectorProxy;

/**
 * Mapa de rotas. As rotas sem token ficam fora do grupo protegido de propósito; o grupo
 * protegido tem um único middleware JWT, sem lista de exceções.
 */
final class Routes
{
    /**
     * @param App<\Psr\Container\ContainerInterface> $app
     */
    public static function register(App $app, bool $docs): void
    {
        $app->group('/api/v1', function (RouteCollectorProxy $v1): void {
            // Públicas
            $v1->get('/restaurants/{id}/menu', [MenuController::class, 'show']);
            $v1->post('/auth/login', [AuthController::class, 'login']);
            $v1->post('/auth/forgot-password', [AuthController::class, 'forgotPassword']);
            $v1->post('/auth/reset-password', [AuthController::class, 'resetPassword']);

            // Protegidas
            $v1->group('', function (RouteCollectorProxy $auth): void {
                $auth->get('/products', [ProductController::class, 'index']);
                $auth->post('/products', [ProductController::class, 'store']);
                $auth->put('/products/order', [ProductController::class, 'order']);
                $auth->get('/products/{id}', [ProductController::class, 'show']);
                $auth->put('/products/{id}', [ProductController::class, 'update']);
                $auth->patch('/products/{id}/visibility', [ProductController::class, 'visibility']);
                $auth->delete('/products/{id}', [ProductController::class, 'destroy']);

                $auth->get('/promotions', [PromotionController::class, 'index']);
                $auth->post('/promotions', [PromotionController::class, 'store']);
                $auth->get('/promotions/{id}', [PromotionController::class, 'show']);
                $auth->put('/promotions/{id}', [PromotionController::class, 'update']);
                $auth->delete('/promotions/{id}', [PromotionController::class, 'destroy']);

                $auth->get('/users', [UserController::class, 'index']);
                $auth->post('/users', [UserController::class, 'store']);
                $auth->delete('/users/{id}', [UserController::class, 'destroy']);
            })->add(JwtMiddleware::class);
        });

        if ($docs) {
            $app->get('/docs', [DocsController::class, 'ui']);
            $app->get('/docs/openapi.json', [DocsController::class, 'spec']);
        }
    }
}
