<?php

declare(strict_types=1);

namespace Goomer\Shared\Http;

use Psr\Container\ContainerInterface;
use Slim\App;
use Slim\Factory\AppFactory;

/**
 * Monta a aplicação Slim a partir do container (usado pelo public/index.php e pelos testes de ponta a ponta).
 */
final class HttpApp
{
    /**
     * @return App<ContainerInterface>
     */
    public static function create(ContainerInterface $container): App
    {
        $app = AppFactory::createFromContainer($container);
        $docs = $container->get('settings.docs') === true;

        Routes::register($app, $docs);
        $app->addRoutingMiddleware();
        $app->add(ProblemDetailsMiddleware::class);

        return $app;
    }
}
