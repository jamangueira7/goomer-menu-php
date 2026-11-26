<?php

declare(strict_types=1);

namespace Goomer\Promotion\Application;

use Goomer\Product\Domain\ProductNotFound;
use Goomer\Product\Domain\ProductRepository;
use Goomer\Promotion\Domain\Promotion;
use Goomer\Promotion\Domain\PromotionRepository;
use Goomer\Shared\Application\Audit\Actor;
use Goomer\Shared\Application\Audit\Auditor;
use Goomer\Shared\Application\TransactionManager;

final readonly class CreatePromotionUseCase
{
    public function __construct(
        private ProductRepository $products,
        private PromotionRepository $promotions,
        private TransactionManager $transaction,
        private Auditor $audit,
    ) {}

    public function execute(Actor $actor, int $productId, PromotionData $data): PromotionOutput
    {
        $product = $this->products->find($actor->restaurantId, $productId) ?? throw ProductNotFound::withId($productId);

        $promotion = Promotion::create(
            $this->promotions->nextId(),
            $product->id(),
            $product->price(),
            $data->description,
            $data->price(),
            $data->timeWindows(),
        );
        $promotion->assertNoOverlapWith($this->promotions->ofProduct($product->id()));

        return $this->transaction->run(function () use ($actor, $promotion): PromotionOutput {
            $this->promotions->save($promotion);
            $output = PromotionOutput::from($promotion);
            $this->audit->record($actor, 'promotion.created', 'promotion', $promotion->id(), ['after' => $output->toArray()]);

            return $output;
        });
    }
}
