<?php

declare(strict_types=1);

namespace Goomer\User\Application;

use Goomer\Menu\Domain\RestaurantNotFound;
use Goomer\Menu\Domain\RestaurantRepository;
use Goomer\Shared\Application\Audit\AuditLog;
use Goomer\Shared\Domain\Clock;
use Goomer\Shared\Domain\DateRange;
use Goomer\Shared\Domain\Exception\ValidationError;
use Goomer\User\Domain\UserNotFound;
use Goomer\User\Domain\UserRepository;

/**
 * Log de auditoria de um usuário (o ator) no restaurante do token, filtrado por dias no fuso do restaurante.
 * Usuário já excluído continua tendo log; usuário de outro restaurante ou inexistente é 404.
 */
final readonly class ListUserAuditLogsUseCase
{
    public const int MAX_PER_PAGE = 100;

    public function __construct(
        private UserRepository $users,
        private AuditLog $audit,
        private RestaurantRepository $restaurants,
        private Clock $clock,
    ) {}

    public function execute(
        int $restaurantId,
        int $userId,
        ?string $dateIni,
        ?string $dateEnd,
        int $page = 1,
        int $perPage = 50,
    ): AuditLogPage {
        if ($page < 1 || $perPage < 1 || $perPage > self::MAX_PER_PAGE) {
            throw new ValidationError('page precisa ser >= 1 e perPage entre 1 e 100.', 'page');
        }

        $email = $this->users->find($restaurantId, $userId)?->email()->value()
            ?? $this->audit->lastEmailOf($restaurantId, $userId)
            ?? throw UserNotFound::withId($userId);
        $timezone = ($this->restaurants->find($restaurantId) ?? throw RestaurantNotFound::withId($restaurantId))->timezone();

        $range = DateRange::ofLocalDays($dateIni, $dateEnd, $timezone, $this->clock->now());
        $result = $this->audit->ofActor($restaurantId, $userId, $range, $page, $perPage);

        return new AuditLogPage($result['items'], $timezone, $userId, $email, $page, $perPage, $result['total']);
    }
}
