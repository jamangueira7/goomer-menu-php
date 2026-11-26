<?php

declare(strict_types=1);

namespace Goomer\Shared\Infrastructure\Database;

use Goomer\Shared\Application\TransactionManager;

/**
 * Transação do PDO. Repositórios que já abrem a própria transação entram nesta (sem aninhar).
 */
final readonly class PdoTransactionManager implements TransactionManager
{
    public function __construct(private Database $db) {}

    public function run(callable $work): mixed
    {
        return $this->db->transactional($work);
    }
}
