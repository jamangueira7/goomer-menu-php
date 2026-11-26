<?php

declare(strict_types=1);

namespace Goomer\Bootstrap;

use DI\ContainerBuilder;
use Goomer\Auth\Application\AccessTokens;
use Goomer\Auth\Application\ForgotPasswordUseCase;
use Goomer\Auth\Application\Mailer;
use Goomer\Auth\Application\ResetRequestThrottle;
use Goomer\Auth\Domain\PasswordResetTokenRepository;
use Goomer\Auth\Infrastructure\FirebaseAccessTokens;
use Goomer\Auth\Infrastructure\PdoPasswordResetTokenRepository;
use Goomer\Auth\Infrastructure\PdoResetRequestThrottle;
use Goomer\Auth\Infrastructure\SymfonyMailer;
use Goomer\Menu\Application\MenuReader;
use Goomer\Menu\Domain\RestaurantRepository;
use Goomer\Menu\Infrastructure\PdoMenuReader;
use Goomer\Menu\Infrastructure\PdoRestaurantRepository;
use Goomer\Product\Domain\ProductRepository;
use Goomer\Product\Infrastructure\PdoProductRepository;
use Goomer\Promotion\Domain\PromotionRepository;
use Goomer\Promotion\Infrastructure\PdoPromotionRepository;
use Goomer\Shared\Application\Audit\AuditLog;
use Goomer\Shared\Application\TransactionManager;
use Goomer\Shared\Domain\Clock;
use Goomer\Shared\Http\Docs\DocsController;
use Goomer\Shared\Infrastructure\Audit\PdoAuditLog;
use Goomer\Shared\Infrastructure\Database\Database;
use Goomer\Shared\Infrastructure\Database\PdoTransactionManager;
use Goomer\Shared\Infrastructure\SystemClock;
use Goomer\User\Application\PasswordHasher;
use Goomer\User\Domain\UserRepository;
use Goomer\User\Infrastructure\Argon2idPasswordHasher;
use Goomer\User\Infrastructure\PdoUserRepository;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Slim\Psr7\Factory\ResponseFactory;
use Symfony\Component\Mailer\Mailer as SymfonyMailerClient;
use Symfony\Component\Mailer\Transport;

use function DI\autowire;
use function DI\factory;

/**
 * Único ponto de composição: liga cada porta à sua implementação. Os testes trocam entradas via $overrides.
 */
final class Container
{
    /**
     * @param array<string, mixed> $overrides
     */
    public static function create(array $overrides = []): ContainerInterface
    {
        $builder = new ContainerBuilder();
        $builder->useAutowiring(true);
        $builder->addDefinitions(self::definitions());
        if ($overrides !== []) {
            $builder->addDefinitions($overrides);
        }

        return $builder->build();
    }

    /**
     * @return array<string, mixed>
     */
    public static function definitions(): array
    {
        return [
            'settings.docs' => Env::get('APP_ENV', 'prod') === 'dev',

            Database::class => factory(static fn(): Database => Database::connect(
                Env::get('DB_HOST', 'db'),
                (int) Env::get('DB_PORT', '5432'),
                Env::get('DB_NAME', 'goomer'),
                Env::get('DB_USER', 'goomer'),
                Env::get('DB_PASSWORD', 'goomer'),
            )),
            Clock::class => autowire(SystemClock::class),
            ResponseFactoryInterface::class => autowire(ResponseFactory::class),
            TransactionManager::class => autowire(PdoTransactionManager::class),
            AuditLog::class => autowire(PdoAuditLog::class),

            ProductRepository::class => autowire(PdoProductRepository::class),
            PromotionRepository::class => autowire(PdoPromotionRepository::class),
            RestaurantRepository::class => autowire(PdoRestaurantRepository::class),
            MenuReader::class => autowire(PdoMenuReader::class),
            UserRepository::class => autowire(PdoUserRepository::class),
            PasswordHasher::class => autowire(Argon2idPasswordHasher::class),
            PasswordResetTokenRepository::class => autowire(PdoPasswordResetTokenRepository::class),
            ResetRequestThrottle::class => autowire(PdoResetRequestThrottle::class),

            AccessTokens::class => factory(static fn(Clock $clock): AccessTokens => new FirebaseAccessTokens(
                Env::required('JWT_SECRET'),
                $clock,
            )),
            Mailer::class => factory(static fn(): Mailer => new SymfonyMailer(
                new SymfonyMailerClient(Transport::fromDsn(Env::get('MAILER_DSN', 'smtp://mailpit:1025'))),
                Env::get('MAIL_FROM', 'Goomer Menu <no-reply@goomer.local>'),
            )),
            ForgotPasswordUseCase::class => autowire()
                ->constructorParameter('resetUrl', Env::get('APP_RESET_URL', 'http://localhost:8090/reset-password')),
            DocsController::class => autowire()->constructorParameter('sourceDir', dirname(__DIR__)),
        ];
    }
}
