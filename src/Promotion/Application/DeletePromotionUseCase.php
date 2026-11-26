<?php

declare(strict_types=1);

namespace Goomer\Promotion\Application;

use Goomer\Promotion\Domain\PromotionNotFound;
use Goomer\Promotion\Domain\PromotionRepository;
use Goomer\Shared\Application\Audit\Actor;
use Goomer\Shared\Application\Audit\Auditor;
use Goomer\Shared\Application\TransactionManager;

final readonly class DeletePromotionUseCase
{
    public function __construct(
        private PromotionRepository $promotions,
        private TransactionManager $transaction,
        private Auditor $audit,
    ) {}

    public function execute(Actor $actor, int $id): void
    {
        $promotion = $this->promotions->find($actor->restaurantId, $id) ?? throw PromotionNotFound::withId($id);

        $this->transaction->run(function () use ($actor, $promotion): void {
            $this->promotions->delete($promotion->id());
            $this->audit->record($actor, 'promotion.deleted', 'promotion', $promotion->id(), [
                'before' => PromotionOutput::from($promotion)->toArray(),
            ]);
        });
    }
}
