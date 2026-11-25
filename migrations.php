<?php

declare(strict_types=1);

// Configuração do Doctrine Migrations (só o esquema; as consultas da aplicação são SQL à mão com PDO).
return [
    'table_storage' => ['table_name' => 'doctrine_migration_versions'],
    'migrations_paths' => ['Goomer\Migrations' => __DIR__ . '/migrations'],
    'all_or_nothing' => true,
    'transactional' => true,
    'check_database_platform' => false,
];
