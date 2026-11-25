<?php

declare(strict_types=1);

namespace Goomer\Tests\Integration;

use Goomer\Auth\Domain\PasswordResetToken;
use Goomer\Auth\Infrastructure\FirebaseAccessTokens;
use Goomer\Auth\Infrastructure\PdoPasswordResetTokenRepository;
use Goomer\Auth\Infrastructure\PdoResetRequestThrottle;
use Goomer\Shared\Application\Exception\Unauthenticated;
use Goomer\Tests\Support\FixedClock;
use Goomer\Tests\Support\TestDatabase;
use Goomer\User\Domain\Email;
use Goomer\User\Domain\EmailAlreadyTaken;
use Goomer\User\Domain\Password;
use Goomer\User\Domain\User;
use Goomer\User\Infrastructure\Argon2idPasswordHasher;
use Goomer\User\Infrastructure\PdoUserRepository;
use PHPUnit\Framework\TestCase;

final class UserAndAuthRepositoryTest extends TestCase
{
    private PdoUserRepository $users;

    protected function setUp(): void
    {
        $this->users = new PdoUserRepository(TestDatabase::reset());
    }

    private function user(int $restaurant, string $email): User
    {
        $user = User::register($this->users->nextId(), $restaurant, 'Pessoa', Email::fromString($email), 'hash');
        $this->users->save($user);

        return $user;
    }

    public function test_user_repository_contract(): void
    {
        $ana = $this->user(1, 'ana@bar.com');
        $this->user(2, 'bia@rest.com');

        self::assertSame($ana->id(), $this->users->findByEmail(Email::fromString('ANA@bar.com'))?->id());
        self::assertNotNull($this->users->find(1, $ana->id()));
        self::assertNull($this->users->find(2, $ana->id()));
        self::assertCount(1, $this->users->ofRestaurant(1));

        $ana->changePassword('novo-hash');
        $this->users->save($ana);
        self::assertSame('novo-hash', $this->users->findById($ana->id())?->passwordHash());

        $this->users->delete($ana->id());
        self::assertNull($this->users->findById($ana->id()));
    }

    public function test_unique_email_is_enforced_by_database(): void
    {
        $this->user(1, 'ana@bar.com');
        $this->expectException(EmailAlreadyTaken::class);
        $this->user(2, 'ana@bar.com');
    }

    public function test_reset_tokens_and_throttle(): void
    {
        $db = TestDatabase::db();
        $tokens = new PdoPasswordResetTokenRepository($db);
        $user = $this->user(1, 'ana@bar.com');
        $now = new \DateTimeImmutable('2026-12-02T12:00:00Z');

        $tokens->save(PasswordResetToken::issue($user->id(), 'abc', $now));
        $found = $tokens->findByHash(hash('sha256', 'abc'));
        self::assertNotNull($found);
        self::assertTrue($found->isUsableAt($now->modify('+29 minutes')));
        self::assertSame(1, $db->fetchInt("SELECT count(*) FROM password_reset_tokens WHERE token_hash <> 'abc'"), 'texto puro nunca é gravado');

        $tokens->invalidateOpenTokensOf($user->id(), $now);
        self::assertFalse($tokens->findByHash(hash('sha256', 'abc'))?->isUsableAt($now) ?? true);
        self::assertNull($tokens->findByHash('nada'));

        $throttle = new PdoResetRequestThrottle($db);
        $throttle->record('ana@bar.com', '10.0.0.1', $now);
        $throttle->record('ana@bar.com', '10.0.0.2', $now->modify('-20 minutes'));
        self::assertSame(1, $throttle->countByEmailSince('ana@bar.com', $now->modify('-15 minutes')));
        self::assertSame(1, $throttle->countByIpSince('10.0.0.1', $now->modify('-15 minutes')));
    }

    public function test_argon2id_hasher_and_jwt(): void
    {
        $hasher = new Argon2idPasswordHasher();
        $hash = $hasher->hash(Password::fromString('senha-forte'));
        self::assertStringStartsWith('$argon2id$', $hash);
        self::assertTrue($hasher->verify('senha-forte', $hash));
        self::assertFalse($hasher->verify('errada', $hash));

        $clock = new FixedClock('2026-12-02T12:00:00Z');
        $jwt = new FirebaseAccessTokens(str_repeat('s', 32), $clock);
        $context = $jwt->verify($jwt->issue(5, 1));
        self::assertSame([5, 1], [$context->userId, $context->restaurantId]);

        $clock->advance('+61 minutes');
        $this->expectException(Unauthenticated::class);
        $jwt->verify($jwt->issue(5, 1) . 'x');
    }

    public function test_jwt_expires_after_one_hour(): void
    {
        $clock = new FixedClock('2026-12-02T12:00:00Z');
        $jwt = new FirebaseAccessTokens(str_repeat('s', 32), $clock);
        $token = $jwt->issue(5, 1);
        $clock->advance('+61 minutes');

        $this->expectException(Unauthenticated::class);
        $jwt->verify($token);
    }
}
