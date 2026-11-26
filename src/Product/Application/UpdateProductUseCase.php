<?php

declare(strict_types=1);

namespace Goomer\Product\Application;

use Goomer\Product\Domain\ProductNotFound;
use Goomer\Product\Domain\ProductRepository;
use Goomer\Shared\Application\Audit\Actor;
use Goomer\Shared\Application\Audit\Auditor;
use Goomer\Shared\Application\TransactionManager;

final readonly class UpdateProductUseCase
{
    public function __construct(
        private ProductRepository $products,
        private TransactionManager $transaction,
        private Auditor $audit,
    ) {}

    public function execute(Actor $actor, int $id, ProductData $data): ProductOutput
    {
        $product = $this->products->find($actor->restaurantId, $id) ?? throw ProductNotFound::withId($id);
        $before = ProductOutput::from($product)->toArray();
        $product->update($data->name, $data->price(), $data->categoryEnum(), $data->visible, $data->windows());

        return $this->transaction->run(function () use ($actor, $product, $before): ProductOutput {
            $this->products->save($product);
            $output = ProductOutput::from($product);
            $this->audit->record($actor, 'product.updated', 'product', $product->id(), Auditor::diff($before, $output->toArray()));

            return $output;
        });
    }
}
