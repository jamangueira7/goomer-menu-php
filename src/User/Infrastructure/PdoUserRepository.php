<?php

declare(strict_types=1);

namespace Goomer\User\Infrastructure;

use Goomer\Shared\Infrastructure\Database\Database;
use Goomer\Shared\Infrastructure\Database\Row;
use Goomer\User\Domain\Email;
use Goomer\User\Domain\EmailAlreadyTaken;
use Goomer\User\Domain\User;
use Goomer\User\Domain\UserRepository;

final readonly class PdoUserRepository implements UserRepository
{
    private const string UNIQUE_VIOLATION = '23505';
    private const string COLUMNS = 'id, restaurant_id, name, email, password_hash';

    public function __construct(private Database $db) {}

    public function nextId(): int
    {
        return $this->db->fetchInt("SELECT nextval(pg_get_serial_sequence('users', 'id'))");
    }

    public function save(User $user): void
    {
        try {
            $this->db->execute(
                <<<'SQL'
                    INSERT INTO users (id, restaurant_id, name, email, password_hash)
                    VALUES (:id, :restaurant_id, :name, :email, :password_hash)
                    ON CONFLICT (id) DO UPDATE SET
                        name = EXCLUDED.name,
                        email = EXCLUDED.email,
                        password_hash = EXCLUDED.password_hash,
                        updated_at = now()
                    SQL,
                [
                    'id' => $user->id(),
                    'restaurant_id' => $user->restaurantId(),
                    'name' => $user->name(),
                    'email' => $user->email()->value(),
                    'password_hash' => $user->passwordHash(),
                ],
            );
        } catch (\PDOException $e) {
            if ($e->getCode() === self::UNIQUE_VIOLATION) {
                throw EmailAlreadyTaken::of($user->email());
            }
            throw $e;
        }
    }

    public function findByEmail(Email $email): ?User
    {
        return $this->one('SELECT ' . self::COLUMNS . ' FROM users WHERE email = :email', ['email' => $email->value()]);
    }

    public function findById(int $id): ?User
    {
        return $this->one('SELECT ' . self::COLUMNS . ' FROM users WHERE id = :id', ['id' => $id]);
    }

    public function find(int $restaurantId, int $id): ?User
    {
        return $this->one(
            'SELECT ' . self::COLUMNS . ' FROM users WHERE id = :id AND restaurant_id = :restaurant_id',
            ['id' => $id, 'restaurant_id' => $restaurantId],
        );
    }

    public function ofRestaurant(int $restaurantId): array
    {
        return array_map(
            $this->hydrate(...),
            $this->db->fetchAll(
                'SELECT ' . self::COLUMNS . ' FROM users WHERE restaurant_id = :restaurant_id ORDER BY id',
                ['restaurant_id' => $restaurantId],
            ),
        );
    }

    public function delete(int $id): void
    {
        $this->db->execute('DELETE FROM users WHERE id = :id', ['id' => $id]);
    }

    /**
     * @param array<string, scalar|null> $params
     */
    private function one(string $sql, array $params): ?User
    {
        $row = $this->db->fetchOne($sql, $params);

        return $row === null ? null : $this->hydrate($row);
    }

    private function hydrate(Row $row): User
    {
        return User::restore(
            $row->int('id'),
            $row->int('restaurant_id'),
            $row->string('name'),
            Email::fromString($row->string('email')),
            $row->string('password_hash'),
        );
    }
}
