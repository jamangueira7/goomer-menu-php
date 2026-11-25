<?php

declare(strict_types=1);

namespace Goomer\Tests\E2E;

use Goomer\Bootstrap\Env;

final class AuthAndUsersApiTest extends ApiTestCase
{
    public function test_users_crud(): void
    {
        $token = $this->login();

        $create = $this->request('POST', '/api/v1/users', ['name' => 'Bia', 'email' => 'bia@bardojoao.com', 'password' => 'senha-da-bia'], $token);
        self::assertSame(201, $create->getStatusCode(), (string) $create->getBody());
        $bia = $this->json($create);
        self::assertArrayNotHasKey('passwordHash', $bia);
        $biaId = $bia['id'] ?? null;
        self::assertIsInt($biaId);

        self::assertSame(409, $this->request('POST', '/api/v1/users', ['name' => 'Bia', 'email' => 'BIA@bardojoao.com', 'password' => 'senha-da-bia'], $token)->getStatusCode());
        self::assertSame(422, $this->request('POST', '/api/v1/users', ['name' => 'Curta', 'email' => 'c@bardojoao.com', 'password' => '123'], $token)->getStatusCode());

        $list = $this->json($this->request('GET', '/api/v1/users', null, $token))['data'];
        self::assertIsArray($list);
        self::assertCount(2, $list);
        self::assertStringNotContainsString('argon2', json_encode($list, JSON_THROW_ON_ERROR));

        self::assertSame(200, $this->request('POST', '/api/v1/auth/login', ['email' => 'bia@bardojoao.com', 'password' => 'senha-da-bia'])->getStatusCode());

        $me = $this->login();
        $adminId = $this->db->fetchInt("SELECT id FROM users WHERE email = 'admin@bardojoao.com'");
        self::assertSame(422, $this->request('DELETE', "/api/v1/users/{$adminId}", null, $me)->getStatusCode());
        self::assertSame(204, $this->request('DELETE', "/api/v1/users/{$biaId}", null, $me)->getStatusCode());
        self::assertSame(404, $this->request('DELETE', "/api/v1/users/{$biaId}", null, $me)->getStatusCode());
    }

    public function test_forgot_and_reset_password_through_mailpit(): void
    {
        $this->clearMailpit();

        $response = $this->request('POST', '/api/v1/auth/forgot-password', ['email' => self::ADMIN_EMAIL]);
        self::assertSame(202, $response->getStatusCode());
        $unknown = $this->request('POST', '/api/v1/auth/forgot-password', ['email' => 'ninguem@bardojoao.com']);
        self::assertSame(202, $unknown->getStatusCode());
        self::assertSame((string) $response->getBody(), (string) $unknown->getBody(), 'mesma resposta, exista ou não');

        $token = $this->tokenFromMailpit(self::ADMIN_EMAIL);

        self::assertSame(422, $this->request('POST', '/api/v1/auth/reset-password', ['token' => $token, 'password' => 'curta'])->getStatusCode());
        self::assertSame(204, $this->request('POST', '/api/v1/auth/reset-password', ['token' => $token, 'password' => 'senha-nova-123'])->getStatusCode());
        self::assertSame(422, $this->request('POST', '/api/v1/auth/reset-password', ['token' => $token, 'password' => 'outra-senha-123'])->getStatusCode(), 'uso único');

        self::assertSame(401, $this->request('POST', '/api/v1/auth/login', ['email' => self::ADMIN_EMAIL, 'password' => self::ADMIN_PASSWORD])->getStatusCode());
        $this->login(self::ADMIN_EMAIL, 'senha-nova-123');
    }

    public function test_forgot_password_rate_limit(): void
    {
        $this->remoteAddr = '10.1.1.1';
        for ($i = 0; $i < 5; ++$i) {
            self::assertSame(202, $this->request('POST', '/api/v1/auth/forgot-password', ['email' => "x{$i}@bardojoao.com"])->getStatusCode());
        }

        $blocked = $this->request('POST', '/api/v1/auth/forgot-password', ['email' => 'y@bardojoao.com']);
        self::assertSame(429, $blocked->getStatusCode());
        self::assertSame('900', $blocked->getHeaderLine('Retry-After'));

        $this->clock->advance('+16 minutes');
        self::assertSame(202, $this->request('POST', '/api/v1/auth/forgot-password', ['email' => 'y@bardojoao.com'])->getStatusCode());
    }

    private function mailpit(): string
    {
        return rtrim(Env::get('MAILPIT_URL', 'http://mailpit:8025'), '/');
    }

    private function clearMailpit(): void
    {
        $context = stream_context_create(['http' => ['method' => 'DELETE']]);
        @file_get_contents($this->mailpit() . '/api/v1/messages', false, $context);
    }

    private function tokenFromMailpit(string $to): string
    {
        for ($attempt = 0; $attempt < 20; ++$attempt) {
            $raw = (string) @file_get_contents($this->mailpit() . '/api/v1/search?query=' . rawurlencode("to:{$to}"));
            if (preg_match('/"ID"\s*:\s*"([^"]+)"/', $raw, $found) === 1) {
                $message = (string) file_get_contents($this->mailpit() . "/api/v1/message/{$found[1]}");
                if (preg_match('/token=([a-f0-9]{64})/', $message, $m) === 1) {
                    return $m[1];
                }
                self::fail('E-mail sem token: ' . $message);
            }
            usleep(100_000);
        }
        self::fail("Nenhum e-mail para {$to} no Mailpit.");
    }
}
