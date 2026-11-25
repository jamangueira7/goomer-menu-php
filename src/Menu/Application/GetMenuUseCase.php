<?php

declare(strict_types=1);

namespace Goomer\Menu\Application;

use Goomer\Menu\Domain\RestaurantNotFound;
use Goomer\Menu\Domain\RestaurantRepository;
use Goomer\Product\Domain\Category;
use Goomer\Shared\Domain\Clock;

final readonly class GetMenuUseCase
{
    public function __construct(
        private RestaurantRepository $restaurants,
        private MenuReader $reader,
        private Clock $clock,
    ) {}

    /**
     * @return array{
     *     restaurant: array{id: int, name: string, timezone: string},
     *     generatedAt: string,
     *     categories: list<array{key: string, name: string, products: list<array<string, mixed>>}>
     * }
     */
    public function execute(int $restaurantId): array
    {
        $restaurant = $this->restaurants->find($restaurantId) ?? throw RestaurantNotFound::withId($restaurantId);
        $moment = $restaurant->timezone()->localize($this->clock->now());

        $grouped = [];
        foreach ($this->reader->itemsAt($restaurant->id(), $moment) as $item) {
            $grouped[$item->category][] = [
                'id' => $item->id,
                'name' => $item->name,
                'price' => $item->price,
                'promotion' => $item->promotionPrice === null ? null : [
                    'description' => $item->promotionDescription,
                    'price' => $item->promotionPrice,
                    'endsAt' => $item->promotionEndsAt,
                ],
            ];
        }

        $categories = [];
        foreach (Category::cases() as $category) {
            if (isset($grouped[$category->value])) {
                $categories[] = ['key' => $category->value, 'name' => $category->label(), 'products' => $grouped[$category->value]];
            }
        }

        return [
            'restaurant' => [
                'id' => $restaurant->id(),
                'name' => $restaurant->name(),
                'timezone' => $restaurant->timezone()->name(),
            ],
            'generatedAt' => $moment->dateTime()->format(\DATE_ATOM),
            'categories' => $categories,
        ];
    }
}
