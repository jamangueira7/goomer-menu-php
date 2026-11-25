<?php

declare(strict_types=1);

// Conexão DBAL usada só pelo Doctrine Migrations; os valores vêm das variáveis de ambiente.
$env = static fn(string $name, string $default): string => is_string(getenv($name)) && getenv($name) !== ''
    ? (string) getenv($name)
    : $default;

return [
    'driver' => 'pdo_pgsql',
    'host' => $env('DB_HOST', 'db'),
    'port' => (int) $env('DB_PORT', '5432'),
    'dbname' => $env('DB_NAME', 'goomer'),
    'user' => $env('DB_USER', 'goomer'),
    'password' => $env('DB_PASSWORD', 'goomer'),
];
