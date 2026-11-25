<?php

declare(strict_types=1);

namespace Goomer\Auth\Infrastructure;

use Goomer\Auth\Application\ResetRequestThrottle;
use Goomer\Shared\Infrastructure\Database\Database;

final readonly class PdoResetRequestThrottle implements ResetRequestThrottle
{
    public function __construct(private Database $db) {}

    public function countByEmailSince(string $email, \DateTimeImmutable $since): int
    {
        return $this->db->fetchInt(
            'SELECT count(*) FROM password_reset_requests WHERE email = :email AND requested_at > CAST(:since AS timestamptz)',
            ['email' => $email, 'since' => $since->format(\DATE_ATOM)],
        );
    }

    public function countByIpSince(string $ip, \DateTimeImmutable $since): int
    {
        return $this->db->fetchInt(
            'SELECT count(*) FROM password_reset_requests WHERE ip = :ip AND requested_at > CAST(:since AS timestamptz)',
            ['ip' => $ip, 'since' => $since->format(\DATE_ATOM)],
        );
    }

    public function record(string $email, string $ip, \DateTimeImmutable $at): void
    {
        $this->db->execute(
            'INSERT INTO password_reset_requests (email, ip, requested_at) VALUES (:email, :ip, CAST(:at AS timestamptz))',
            ['email' => $email, 'ip' => $ip, 'at' => $at->format(\DATE_ATOM)],
        );
    }
}
