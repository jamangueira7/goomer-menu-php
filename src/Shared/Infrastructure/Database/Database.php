<?php

declare(strict_types=1);

namespace Goomer\Shared\Infrastructure\Database;

/**
 * Embrulho fino sobre o PDO: só prepared statements, linhas como array associativo e transações.
 */
final readonly class Database
{
    public function __construct(private \PDO $pdo) {}

    public static function connect(string $host, int $port, string $name, string $user, string $password): self
    {
        $pdo = new \PDO(
            sprintf('pgsql:host=%s;port=%d;dbname=%s', $host, $port, $name),
            $user,
            $password,
            [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                \PDO::ATTR_EMULATE_PREPARES => false,
            ],
        );

        return new self($pdo);
    }

    /**
     * @param array<string, scalar|null> $params
     */
    public function execute(string $sql, array $params = []): \PDOStatement
    {
        $statement = $this->pdo->prepare($sql);
        foreach ($params as $name => $value) {
            $type = match (true) {
                is_int($value) => \PDO::PARAM_INT,
                is_bool($value) => \PDO::PARAM_BOOL,
                $value === null => \PDO::PARAM_NULL,
                default => \PDO::PARAM_STR,
            };
            $statement->bindValue(':' . $name, $value, $type);
        }
        $statement->execute();

        return $statement;
    }

    /**
     * @param array<string, scalar|null> $params
     *
     * @return list<Row>
     */
    public function fetchAll(string $sql, array $params = []): array
    {
        $rows = [];
        foreach ($this->execute($sql, $params)->fetchAll() as $row) {
            $rows[] = Row::from($row);
        }

        return $rows;
    }

    /**
     * @param array<string, scalar|null> $params
     */
    public function fetchOne(string $sql, array $params = []): ?Row
    {
        $row = $this->execute($sql, $params)->fetch();

        return $row === false ? null : Row::from($row);
    }

    /**
     * @param array<string, scalar|null> $params
     */
    public function fetchInt(string $sql, array $params = []): int
    {
        $value = $this->execute($sql, $params)->fetchColumn();

        return is_numeric($value) ? (int) $value : 0;
    }

    /**
     * @template T
     *
     * @param callable(): T $work
     *
     * @return T
     */
    public function transactional(callable $work): mixed
    {
        if ($this->pdo->inTransaction()) {
            return $work();
        }
        $this->pdo->beginTransaction();
        try {
            $result = $work();
            $this->pdo->commit();

            return $result;
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Formata uma lista de inteiros como literal de array do Postgres, para usar com `= ANY(:ids::bigint[])`.
     *
     * @param list<int> $ids
     */
    public static function intArray(array $ids): string
    {
        return '{' . implode(',', $ids) . '}';
    }
}
