<?php

declare(strict_types=1);

namespace Goomer\Tests\Support;

use Goomer\Bootstrap\Env;
use Goomer\Shared\Infrastructure\Database\Database;

/**
 * Banco `goomer_test` no Postgres do compose: criado e migrado uma vez por execução, limpo a cada teste.
 * (Substitui o Testcontainers da especificação; ver "Desafios encontrados" no README.)
 */
final class TestDatabase
{
    private static ?Database $db = null;

    public static function name(): string
    {
        $name = Env::get('DB_NAME', 'goomer_test');
        if (!str_ends_with($name, '_test')) {
            throw new \RuntimeException("Recusando rodar testes no banco \"{$name}\": o nome precisa terminar em _test.");
        }

        return $name;
    }

    public static function db(): Database
    {
        if (self::$db !== null) {
            return self::$db;
        }

        $name = self::name();
        $host = Env::get('DB_HOST', 'db');
        $port = (int) Env::get('DB_PORT', '5432');
        $user = Env::get('DB_USER', 'goomer');
        $password = Env::get('DB_PASSWORD', 'goomer');

        $admin = Database::connect($host, $port, 'postgres', $user, $password);
        if ($admin->fetchInt('SELECT count(*) FROM pg_database WHERE datname = :name', ['name' => $name]) === 0) {
            $admin->execute(sprintf('CREATE DATABASE "%s"', $name));
        }

        $command = sprintf(
            'DB_NAME=%s %s/vendor/bin/doctrine-migrations migrate --no-interaction --allow-no-migration --quiet 2>&1',
            escapeshellarg($name),
            escapeshellarg(dirname(__DIR__, 2)),
        );
        exec($command, $output, $code);
        if ($code !== 0) {
            throw new \RuntimeException("Falha ao migrar {$name}:\n" . implode("\n", $output));
        }

        return self::$db = Database::connect($host, $port, $name, $user, $password);
    }

    /** Limpa tudo e recria os restaurantes 1 (São Paulo) e 2 (Tóquio). */
    public static function reset(): Database
    {
        $db = self::db();
        $db->execute('TRUNCATE restaurants, products, product_availability, promotions, promotion_windows, users,
            password_reset_tokens, password_reset_requests RESTART IDENTITY CASCADE');
        $db->execute("INSERT INTO restaurants (id, name, timezone) VALUES
            (1, 'Bar do João', 'America/Sao_Paulo'), (2, 'Izakaya', 'Asia/Tokyo')");
        $db->execute("SELECT setval(pg_get_serial_sequence('restaurants', 'id'), 2)");

        return $db;
    }
}
