<?php

declare(strict_types=1);

namespace Goomer\Product\Application;

use Goomer\Product\Domain\ProductRepository;
use Goomer\Shared\Domain\Exception\ValidationError;

/**
 * Recebe os ids na nova ordem do cardápio. Os ids listados ganham as posições 1..n; os que ficaram de fora
 * vão para depois deles, mantendo a ordem relativa que já tinham.
 */
final readonly class ReorderProductsUseCase
{
    public function __construct(private ProductRepository $products) {}

    /**
     * @param list<int> $ids
     */
    public function execute(int $restaurantId, array $ids): void
    {
        if ($ids === []) {
            throw new ValidationError('Informe ao menos um id de produto.', 'ids');
        }
        if (count($ids) !== count(array_unique($ids))) {
            throw new ValidationError('A lista de ids tem repetições.', 'ids');
        }

        $existing = $this->products->idsOf($restaurantId);
        $unknown = array_values(array_diff($ids, $existing));
        if ($unknown !== []) {
            throw new ValidationError(sprintf('Produtos inexistentes: %s.', implode(', ', $unknown)), 'ids');
        }

        $ordered = [...$ids, ...array_values(array_diff($existing, $ids))];
        $positions = [];
        foreach ($ordered as $index => $id) {
            $positions[$id] = $index + 1;
        }
        $this->products->savePositions($restaurantId, $positions);
    }
}
