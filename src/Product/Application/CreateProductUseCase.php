<?php

declare(strict_types=1);

namespace Goomer\Product\Application;

use Goomer\Product\Domain\Product;
use Goomer\Product\Domain\ProductRepository;
use Goomer\Shared\Application\Audit\Actor;
use Goomer\Shared\Application\Audit\Auditor;
use Goomer\Shared\Application\TransactionManager;

final readonly class CreateProductUseCase
{
    public function __construct(
        private ProductRepository $products,
        private TransactionManager $transaction,
        private Auditor $audit,
    ) {}

    public function execute(Actor $actor, ProductData $data): ProductOutput
    {
        $product = Product::create(
            $this->products->nextId(),
            $actor->restaurantId,
            $data->name,
            $data->price(),
            $data->categoryEnum(),
            $data->visible,
            $this->products->lastPosition($actor->restaurantId) + 1,
            $data->windows(),
        );

        return $this->transaction->run(function () use ($actor, $product): ProductOutput {
            $this->products->save($product);
            $output = ProductOutput::from($product);
            $this->audit->record($actor, 'product.created', 'product', $product->id(), ['after' => $output->toArray()]);

            return $output;
        });
    }
}
