<?php

declare(strict_types=1);

namespace Goomer\Auth\Application;

use Goomer\Shared\Application\Exception\Unauthenticated;
use Goomer\Shared\Domain\Exception\ValidationError;
use Goomer\User\Application\PasswordHasher;
use Goomer\User\Domain\Email;
use Goomer\User\Domain\UserRepository;

final readonly class LoginUseCase
{
    public function __construct(
        private UserRepository $users,
        private PasswordHasher $hasher,
        private AccessTokens $tokens,
    ) {}

    /**
     * @return array{accessToken: string, tokenType: string, expiresIn: int}
     */
    public function execute(string $email, string $password): array
    {
        try {
            $user = $this->users->findByEmail(Email::fromString($email));
        } catch (ValidationError) {
            $user = null;
        }

        if ($user === null || !$this->hasher->verify($password, $user->passwordHash())) {
            throw new Unauthenticated('E-mail ou senha inválidos.');
        }

        return [
            'accessToken' => $this->tokens->issue($user->id(), $user->restaurantId()),
            'tokenType' => 'Bearer',
            'expiresIn' => AccessTokens::TTL_SECONDS,
        ];
    }
}
