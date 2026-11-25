<?php

declare(strict_types=1);

namespace Goomer\Shared\Http\Docs;

use OpenApi\Attributes as OA;

/**
 * Metadados do OpenAPI; as rotas vêm dos atributos nos controllers.
 */
#[OA\Info(version: '1.0.0', description: 'Produtos, promoções e cardápio de restaurante. Erros em application/problem+json; preços em centavos.', title: 'Goomer Menu API (PHP)')]
#[OA\Server(url: 'http://localhost:8090')]
#[OA\SecurityScheme(securityScheme: 'bearerAuth', type: 'http', bearerFormat: 'JWT', scheme: 'bearer')]
#[OA\Schema(
    schema: 'TimeWindow',
    required: ['weekday', 'start', 'end'],
    properties: [
        new OA\Property(property: 'weekday', type: 'integer', maximum: 6, minimum: 0, example: 3),
        new OA\Property(property: 'start', type: 'string', example: '18:00'),
        new OA\Property(property: 'end', type: 'string', example: '20:00'),
    ],
)]
#[OA\Schema(
    schema: 'ProductInput',
    required: ['name', 'price', 'category'],
    properties: [
        new OA\Property(property: 'name', type: 'string', example: 'Chopp 300 ml'),
        new OA\Property(property: 'price', description: 'Centavos', type: 'integer', example: 1400),
        new OA\Property(property: 'category', type: 'string', enum: ['entradas', 'pratos_principais', 'sobremesas', 'bebidas']),
        new OA\Property(property: 'visible', type: 'boolean', default: true),
        new OA\Property(property: 'availability', type: 'array', items: new OA\Items(ref: '#/components/schemas/TimeWindow')),
    ],
)]
#[OA\Schema(
    schema: 'PromotionInput',
    required: ['description', 'price', 'windows'],
    properties: [
        new OA\Property(property: 'productId', description: 'Só na criação', type: 'integer', example: 1),
        new OA\Property(property: 'description', type: 'string', example: 'Happy hour: chopp pela metade do preço'),
        new OA\Property(property: 'price', description: 'Centavos, menor que o preço normal', type: 'integer', example: 700),
        new OA\Property(property: 'windows', type: 'array', items: new OA\Items(ref: '#/components/schemas/TimeWindow')),
    ],
)]
final class OpenApiSpec {}
