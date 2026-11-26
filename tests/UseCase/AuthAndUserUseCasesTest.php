<?php

declare(strict_types=1);

namespace Goomer\Tests\UseCase;

use Goomer\Auth\Application\ForgotPasswordUseCase;
use Goomer\Auth\Application\LoginUseCase;
use Goomer\Auth\Application\ResetPasswordUseCase;
use Goomer\Auth\Domain\InvalidResetToken;
use Goomer\Shared\Application\Exception\TooManyRequests;
use Goomer\Shared\Application\Exception\Unauthenticated;
use Goomer\Tests\Support\AuthFakes;
use Goomer\Tests\Support\FakeHasher;
use Goomer\Tests\Support\FixedClock;
use Goomer\Tests\Support\InMemoryAudit;
use Goomer\Tests\Support\InMemoryUserRepository;
use Goomer\User\Application\CreateUserUseCase;
use Goomer\User\Application\DeleteUserUseCase;
use Goomer\User\Application\ListUsersUseCase;
use Goomer\User\Domain\CannotDeleteSelf;
use Goomer\User\Domain\EmailAlreadyTaken;
use Goomer\User\Domain\UserNotFound;
use PHPUnit\Framework\TestCase;

final class AuthAndUserUseCasesTest extends TestCase
{
    private InMemoryUserRepository $users;
    private AuthFakes $fakes;
    private FixedClock $clock;
    private InMemoryAudit $audit;
    private int $adminId;

    protected function setUp(): void
    {
        $this->users = new InMemoryUserRepository();
        $this->fakes = new AuthFakes();
        $this->clock = new FixedClock('2026-12-02T12:00:00Z');
        $this->audit = new InMemoryAudit($this->clock);
        $this->adminId = $this->createUser(1, 'admin@bar.com')->id;
    }

    private function createUser(int $restaurant, string $email): \Goomer\User\Application\UserOutput
    {
        return (new CreateUserUseCase($this->users, new FakeHasher(), $this->audit, $this->audit->auditor()))
            ->bootstrap($restaurant, 'Pessoa', $email, 'senha-forte');
    }

    private function forgot(): ForgotPasswordUseCase
    {
        return new ForgotPasswordUseCase(
            $this->users,
            $this->fakes,
            $this->fakes,
            $this->fakes,
            $this->clock,
            $this->audit,
            $this->audit->auditor(),
            'http://app/reset?x=1',
        );
    }

    private function reset(): ResetPasswordUseCase
    {
        return new ResetPasswordUseCase($this->users, $this->fakes, new FakeHasher(), $this->clock, $this->audit, $this->audit->auditor());
    }

    public function test_login_returns_token_with_user_and_restaurant(): void
    {
        $login = new LoginUseCase($this->users, new FakeHasher(), $this->fakes);
        $result = $login->execute('ADMIN@bar.com', 'senha-forte');

        self::assertSame("token-{$this->adminId}-1", $result['accessToken']);
        self::assertSame(3600, $result['expiresIn']);
        self::assertSame('Bearer', $result['tokenType']);
    }

    public function test_login_failures_are_401(): void
    {
        $login = new LoginUseCase($this->users, new FakeHasher(), $this->fakes);
        foreach ([['admin@bar.com', 'errada'], ['ninguem@bar.com', 'senha-forte'], ['nao-e-email', 'x']] as [$email, $password]) {
            try {
                $login->execute($email, $password);
                self::fail('Era para recusar ' . $email);
            } catch (Unauthenticated) {
                self::addToAssertionCount(1);
            }
        }
    }

    public function test_users_are_scoped_and_email_is_unique(): void
    {
        $this->createUser(1, 'bia@bar.com');
        $this->createUser(2, 'outro@rest.com');

        $list = (new ListUsersUseCase($this->users))->execute(1);
        self::assertSame(['admin@bar.com', 'bia@bar.com'], array_column(array_map(static fn($u) => $u->toArray(), $list), 'email'));
        self::assertArrayNotHasKey('passwordHash', $list[0]->toArray());

        $this->expectException(EmailAlreadyTaken::class);
        $this->createUser(2, 'Bia@Bar.com');
    }

    public function test_delete_user_rules(): void
    {
        $bia = $this->createUser(1, 'bia@bar.com')->id;
        $other = $this->createUser(2, 'outro@rest.com')->id;
        $delete = new DeleteUserUseCase($this->users, $this->audit, $this->audit->auditor());
        $admin = InMemoryAudit::actor($this->adminId);

        $delete->execute($admin, $bia);
        self::assertNull($this->users->findById($bia));

        try {
            $delete->execute($admin, $other);
            self::fail('Usuário de outro restaurante');
        } catch (UserNotFound) {
            self::addToAssertionCount(1);
        }

        $this->expectException(CannotDeleteSelf::class);
        $delete->execute($admin, $this->adminId);
    }

    public function test_forgot_and_reset_password_flow(): void
    {
        $this->forgot()->execute('admin@bar.com', '10.0.0.1');

        self::assertCount(1, $this->fakes->sent);
        self::assertStringStartsWith('http://app/reset?x=1&token=', $this->fakes->sent[0]['link']);
        $token = $this->fakes->lastToken();
        self::assertSame(64, strlen($token), '32 bytes em hexadecimal');
        self::assertArrayHasKey(hash('sha256', $token), $this->fakes->tokens, 'o banco guarda só o hash');

        $this->clock->advance('+10 minutes');
        $this->reset()->execute($token, 'nova-senha-123');
        self::assertSame('hashed:nova-senha-123', $this->users->findById($this->adminId)?->passwordHash());

        $this->expectException(InvalidResetToken::class);
        $this->reset()->execute($token, 'outra-senha-123');
    }

    public function test_unknown_email_sends_nothing_but_counts(): void
    {
        $this->forgot()->execute('ninguem@bar.com', '10.0.0.1');

        self::assertSame([], $this->fakes->sent);
        self::assertCount(1, $this->fakes->requests);
    }

    public function test_new_request_invalidates_previous_token(): void
    {
        $this->forgot()->execute('admin@bar.com', '10.0.0.1');
        $first = $this->fakes->lastToken();
        $this->forgot()->execute('admin@bar.com', '10.0.0.1');

        $this->expectException(InvalidResetToken::class);
        $this->reset()->execute($first, 'nova-senha-123');
    }

    public function test_expired_or_unknown_token_is_rejected(): void
    {
        $this->forgot()->execute('admin@bar.com', '10.0.0.1');
        $this->clock->advance('+31 minutes');

        try {
            $this->reset()->execute($this->fakes->lastToken(), 'nova-senha-123');
            self::fail('Token vencido');
        } catch (InvalidResetToken) {
            self::addToAssertionCount(1);
        }

        $this->expectException(InvalidResetToken::class);
        $this->reset()->execute('inexistente', 'nova-senha-123');
    }

    public function test_rate_limit_per_email_and_per_ip(): void
    {
        for ($i = 0; $i < 5; ++$i) {
            $this->forgot()->execute('admin@bar.com', "10.0.0.{$i}");
        }
        try {
            $this->forgot()->execute('admin@bar.com', '10.0.0.99');
            self::fail('Sexto pedido do mesmo e-mail');
        } catch (TooManyRequests $e) {
            self::assertSame(900, $e->retryAfterSeconds());
        }

        for ($i = 0; $i < 5; ++$i) {
            $this->forgot()->execute("p{$i}@bar.com", '10.9.9.9');
        }
        try {
            $this->forgot()->execute('outro@bar.com', '10.9.9.9');
            self::fail('Sexto pedido do mesmo IP');
        } catch (TooManyRequests) {
            self::addToAssertionCount(1);
        }

        $this->clock->advance('+16 minutes');
        $this->forgot()->execute('admin@bar.com', '10.9.9.9');
        self::assertCount(6, $this->fakes->sent);
    }
}
