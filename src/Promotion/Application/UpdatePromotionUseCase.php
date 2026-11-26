<?php

declare(strict_types=1);

namespace Goomer\Promotion\Application;

use Goomer\Product\Domain\ProductRepository;
use Goomer\Promotion\Domain\PromotionNotFound;
use Goomer\Promotion\Domain\PromotionRepository;
use Goomer\Shared\Application\Audit\Actor;
use Goomer\Shared\Application\Audit\Auditor;
use Goomer\Shared\Application\TransactionManager;

final readonly class UpdatePromotionUseCase
{
    public function __construct(
        private ProductRepository $products,
        private PromotionRepository $promotions,
        private TransactionManager $transaction,
        private Auditor $audit,
    ) {}

    public function execute(Actor $actor, int $id, PromotionData $data): PromotionOutput
    {
        $promotion = $this->promotions->find($actor->restaurantId, $id) ?? throw PromotionNotFound::withId($id);
        $product = $this->products->find($actor->restaurantId, $promotion->productId()) ?? throw PromotionNotFound::withId($id);
        $before = PromotionOutput::from($promotion)->toArray();

        $promotion->update($product->price(), $data->description, $data->price(), $data->timeWindows());
        $promotion->assertNoOverlapWith($this->promotions->ofProduct($product->id()));

        return $this->transaction->run(function () use ($actor, $promotion, $before): PromotionOutput {
            $this->promotions->save($promotion);
            $output = PromotionOutput::from($promotion);
            $this->audit->record($actor, 'promotion.updated', 'promotion', $promotion->id(), Auditor::diff($before, $output->toArray()));

            return $output;
        });
    }
}
