<?php

declare(strict_types=1);

namespace Goomer\Tests\E2E;

use Goomer\Bootstrap\Container;
use Goomer\Shared\Domain\Clock;
use Goomer\Shared\Infrastructure\Database\Database;
use Goomer\Tests\Support\FixedClock;
use Goomer\Tests\Support\TestDatabase;
use Goomer\User\Application\CreateUserUseCase;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Slim\App;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Factory\StreamFactory;

/**
 * Sobe a aplicação Slim inteira (container real, banco goomer_test, Mailpit) com o relógio fixo
 * numa quarta-feira às 18:30 de São Paulo, e manda requisições PSR-7 direto no handler.
 */
abstract class ApiTestCase extends TestCase
{
    protected const string ADMIN_EMAIL = 'admin@bardojoao.com';
    protected const string ADMIN_PASSWORD = 'goomer123';

    protected Database $db;
    protected FixedClock $clock;
    /** @var App<ContainerInterface> */
    protected App $app;
    protected ContainerInterface $container;
    protected string $remoteAddr = '10.0.0.1';

    protected function setUp(): void
    {
        $this->db = TestDatabase::reset();
        $this->clock = new FixedClock('2026-12-02T21:30:00Z');
        $this->container = Container::create([Clock::class => $this->clock, Database::class => $this->db]);
        $this->app = \Goomer\Shared\Http\HttpApp::create($this->container);

        $users = $this->container->get(CreateUserUseCase::class);
        self::assertInstanceOf(CreateUserUseCase::class, $users);
        $users->execute(1, 'Admin', self::ADMIN_EMAIL, self::ADMIN_PASSWORD);
    }

    /**
     * @param array<string, mixed>|list<mixed>|string|null $body
     */
    protected function request(string $method, string $uri, array|string|null $body = null, ?string $token = null): ResponseInterface
    {
        $request = (new ServerRequestFactory())->createServerRequest($method, $uri, ['REMOTE_ADDR' => $this->remoteAddr]);
        $query = parse_url($uri, PHP_URL_QUERY);
        if (is_string($query)) {
            parse_str($query, $params);
            $request = $request->withQueryParams($params);
        }
        if ($body !== null) {
            $json = is_string($body) ? $body : json_encode($body, JSON_THROW_ON_ERROR);
            $request = $request->withBody((new StreamFactory())->createStream($json))
                ->withHeader('Content-Type', 'application/json');
        }
        if ($token !== null) {
            $request = $request->withHeader('Authorization', 'Bearer ' . $token);
        }

        return $this->app->handle($request);
    }

    /**
     * @return array<array-key, mixed>
     */
    protected function json(ResponseInterface $response): array
    {
        $data = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($data);

        return $data;
    }

    protected function login(string $email = self::ADMIN_EMAIL, string $password = self::ADMIN_PASSWORD): string
    {
        $response = $this->request('POST', '/api/v1/auth/login', ['email' => $email, 'password' => $password]);
        self::assertSame(200, $response->getStatusCode(), (string) $response->getBody());
        $token = $this->json($response)['accessToken'] ?? null;
        self::assertIsString($token);

        return $token;
    }

    /**
     * @param array<string, mixed> $product
     */
    protected function createProduct(string $token, array $product): int
    {
        $response = $this->request('POST', '/api/v1/products', $product, $token);
        self::assertSame(201, $response->getStatusCode(), (string) $response->getBody());
        $id = $this->json($response)['id'] ?? null;
        self::assertIsInt($id);

        return $id;
    }
}
