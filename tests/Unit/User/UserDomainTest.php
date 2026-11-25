<?php

declare(strict_types=1);

namespace Goomer\Tests\Unit\User;

use Goomer\Auth\Domain\InvalidResetToken;
use Goomer\Auth\Domain\PasswordResetToken;
use Goomer\Shared\Domain\Exception\ValidationError;
use Goomer\User\Domain\Email;
use Goomer\User\Domain\Password;
use Goomer\User\Domain\User;
use PHPUnit\Framework\TestCase;

final class UserDomainTest extends TestCase
{
    public function test_email_is_normalized_and_validated(): void
    {
        self::assertSame('ana@bar.com', Email::fromString('  Ana@Bar.COM ')->value());

        $this->expectException(ValidationError::class);
        Email::fromString('ana-at-bar');
    }

    public function test_password_has_minimum_length_and_hides_itself(): void
    {
        $password = Password::fromString('12345678');
        self::assertSame('12345678', $password->plain());
        self::assertStringNotContainsString('12345678', print_r($password, true));

        $this->expectException(ValidationError::class);
        Password::fromString('1234567');
    }

    public function test_password_has_maximum_length(): void
    {
        $this->expectException(ValidationError::class);
        Password::fromString(str_repeat('a', 129));
    }

    public function test_user_registration_and_password_change(): void
    {
        $user = User::register(1, 2, ' Ana ', Email::fromString('ana@bar.com'), 'hash-1');
        self::assertSame('Ana', $user->name());
        self::assertSame(2, $user->restaurantId());
        $user->changePassword('hash-2');
        self::assertSame('hash-2', $user->passwordHash());

        $this->expectException(ValidationError::class);
        User::register(1, 2, '', Email::fromString('ana@bar.com'), 'hash');
    }

    public function test_reset_token_is_single_use_and_expires_in_30_minutes(): void
    {
        $now = new \DateTimeImmutable('2026-12-02T12:00:00Z');
        $token = PasswordResetToken::issue(5, 'segredo', $now);

        self::assertSame(hash('sha256', 'segredo'), $token->tokenHash());
        self::assertSame(5, $token->userId());
        self::assertTrue($token->isUsableAt($now->modify('+29 minutes')));
        self::assertFalse($token->isUsableAt($now->modify('+30 minutes')));

        $token->consume($now->modify('+1 minute'));
        self::assertNotNull($token->usedAt());
        self::assertFalse($token->isUsableAt($now->modify('+2 minutes')));

        $this->expectException(InvalidResetToken::class);
        $token->consume($now->modify('+3 minutes'));
    }
}
