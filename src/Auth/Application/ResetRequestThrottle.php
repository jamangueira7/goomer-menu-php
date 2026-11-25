<?php

declare(strict_types=1);

namespace Goomer\Auth\Application;

/**
 * Registro dos pedidos de recuperação, para o limite por e-mail e por IP.
 */
interface ResetRequestThrottle
{
    public function countByEmailSince(string $email, \DateTimeImmutable $since): int;

    public function countByIpSince(string $ip, \DateTimeImmutable $since): int;

    public function record(string $email, string $ip, \DateTimeImmutable $at): void;
}
