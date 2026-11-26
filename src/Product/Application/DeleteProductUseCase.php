<?php

declare(strict_types=1);

namespace Goomer\Product\Application;

use Goomer\Product\Domain\ProductNotFound;
use Goomer\Product\Domain\ProductRepository;
use Goomer\Promotion\Domain\PromotionRepository;
use Goomer\Shared\Application\Audit\Actor;
use Goomer\Shared\Application\Audit\Auditor;
use Goomer\Shared\Application\TransactionManager;

/**
 * Exclui o produto; as promoções dele saem junto (ON DELETE CASCADE no banco) e o log conta quantas foram.
 */
final readonly class DeleteProductUseCase
{
    public function __construct(
        private ProductRepository $products,
        private PromotionRepository $promotions,
        private TransactionManager $transaction,
        private Auditor $audit,
    ) {}

    public function execute(Actor $actor, int $id): void
    {
        $this->transaction->run(function () use ($actor, $id): void {
            $product = $this->products->find($actor->restaurantId, $id) ?? throw ProductNotFound::withId($id);
            $before = [
                ...ProductOutput::from($product)->toArray(),
                'cascadedPromotions' => count($this->promotions->ofProduct($product->id())),
            ];
            $this->products->delete($actor->restaurantId, $id);
            $this->audit->record($actor, 'product.deleted', 'product', $id, ['before' => $before]);
        });
    }
}
