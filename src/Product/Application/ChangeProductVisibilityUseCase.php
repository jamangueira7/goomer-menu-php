<?php

declare(strict_types=1);

namespace Goomer\Product\Application;

use Goomer\Product\Domain\ProductNotFound;
use Goomer\Product\Domain\ProductRepository;
use Goomer\Shared\Application\Audit\Actor;
use Goomer\Shared\Application\Audit\Auditor;
use Goomer\Shared\Application\TransactionManager;

final readonly class ChangeProductVisibilityUseCase
{
    public function __construct(
        private ProductRepository $products,
        private TransactionManager $transaction,
        private Auditor $audit,
    ) {}

    public function execute(Actor $actor, int $id, bool $visible): ProductOutput
    {
        $product = $this->products->find($actor->restaurantId, $id) ?? throw ProductNotFound::withId($id);
        $wasVisible = $product->isVisible();
        $visible ? $product->show() : $product->hide();

        return $this->transaction->run(function () use ($actor, $product, $wasVisible, $visible): ProductOutput {
            $this->products->save($product);
            $this->audit->record($actor, 'product.visibility_changed', 'product', $product->id(), [
                'before' => ['visible' => $wasVisible],
                'after' => ['visible' => $visible],
            ]);

            return ProductOutput::from($product);
        });
    }
}
