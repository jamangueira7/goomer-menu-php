<?php

declare(strict_types=1);

/*
 * Cria um usuário administrador num restaurante existente.
 * Uso: php bin/create-admin.php --email=ana@bar.com --password=segredo123 [--name="Ana"] [--restaurant=1]
 */

use Goomer\Bootstrap\Container;
use Goomer\Shared\Domain\Exception\DomainError;
use Goomer\User\Application\CreateUserUseCase;

require dirname(__DIR__) . '/vendor/autoload.php';

$options = getopt('', ['email:', 'password:', 'name::', 'restaurant::']);
$email = $options['email'] ?? null;
$password = $options['password'] ?? null;
if (!is_string($email) || !is_string($password)) {
    fwrite(STDERR, "Uso: php bin/create-admin.php --email=<e-mail> --password=<senha> [--name=<nome>] [--restaurant=<id>]\n");
    exit(1);
}
$name = is_string($options['name'] ?? null) ? $options['name'] : 'Administrador';
$restaurant = is_string($options['restaurant'] ?? null) ? (int) $options['restaurant'] : 1;

$useCase = Container::create()->get(CreateUserUseCase::class);
assert($useCase instanceof CreateUserUseCase);

try {
    $user = $useCase->execute($restaurant, $name, $email, $password);
} catch (DomainError $e) {
    fwrite(STDERR, 'Erro: ' . $e->getMessage() . "\n");
    exit(1);
}

echo sprintf("Usuário %d criado: %s (restaurante %d).\n", $user->id, $user->email, $user->restaurantId);
