<?php

declare(strict_types=1);

/*
 * Seed idempotente: restaurante "Bar do João", admin de exemplo e um cardápio pequeno.
 * Usa os próprios casos de uso, então as regras de negócio valem também aqui.
 */

use Goomer\Bootstrap\Container;
use Goomer\Product\Application\CreateProductUseCase;
use Goomer\Product\Application\ProductData;
use Goomer\Promotion\Application\CreatePromotionUseCase;
use Goomer\Promotion\Application\PromotionData;
use Goomer\Shared\Application\Audit\Actor;
use Goomer\Shared\Infrastructure\Database\Database;
use Goomer\User\Application\CreateUserUseCase;

require dirname(__DIR__) . '/vendor/autoload.php';

$container = Container::create();
$db = $container->get(Database::class);
assert($db instanceof Database);

if ($db->fetchInt('SELECT count(*) FROM restaurants WHERE id = 1') > 0) {
    echo "Seed já aplicado; nada a fazer.\n";
    exit(0);
}

$db->execute("INSERT INTO restaurants (id, name, timezone) VALUES (1, 'Bar do João', 'America/Sao_Paulo')");
$db->execute("SELECT setval(pg_get_serial_sequence('restaurants', 'id'), 1)");

$users = $container->get(CreateUserUseCase::class);
assert($users instanceof CreateUserUseCase);
$admin = $users->bootstrap(1, 'Admin do Bar do João', 'admin@bardojoao.com', 'goomer123');
$actor = new Actor($admin->id, $admin->email, $admin->restaurantId);

$products = $container->get(CreateProductUseCase::class);
assert($products instanceof CreateProductUseCase);
$chopp = $products->execute($actor, new ProductData('Chopp 300 ml', 1400, 'bebidas'));
$products->execute($actor, new ProductData('Refrigerante lata', 700, 'bebidas'));
$products->execute($actor, new ProductData('Bolinho de bacalhau (6 un.)', 3200, 'entradas'));
$products->execute($actor, new ProductData('Picanha na chapa', 8900, 'pratos_principais'));
$products->execute($actor, new ProductData('Feijoada (só sábado no almoço)', 6500, 'pratos_principais', true, [
    ['weekday' => 6, 'start' => '11:00', 'end' => '16:00'],
]));
$products->execute($actor, new ProductData('Pudim de leite', 1800, 'sobremesas'));
$products->execute($actor, new ProductData('Prato secreto do chef', 9900, 'pratos_principais', false));

$promotions = $container->get(CreatePromotionUseCase::class);
assert($promotions instanceof CreatePromotionUseCase);
$promotions->execute($actor, $chopp->id, new PromotionData(
    'Happy hour: chopp pela metade do preço',
    700,
    array_map(static fn(int $day): array => ['weekday' => $day, 'start' => '18:00', 'end' => '20:00'], [1, 2, 3, 4, 5]),
));

echo "Seed aplicado: restaurante 1 (Bar do João), admin admin@bardojoao.com / goomer123.\n";
