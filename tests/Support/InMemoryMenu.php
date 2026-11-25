<?php

declare(strict_types=1);

namespace Goomer\Tests\Support;

use Goomer\Menu\Application\MenuItem;
use Goomer\Menu\Application\MenuReader;
use Goomer\Menu\Domain\Restaurant;
use Goomer\Menu\Domain\RestaurantRepository;
use Goomer\Product\Domain\Category;
use Goomer\Product\Domain\Product;
use Goomer\Shared\Domain\LocalMoment;

/**
 * Leitor de cardápio e repositório de restaurantes em memória, com as mesmas regras da consulta SQL.
 */
final class InMemoryMenu implements MenuReader, RestaurantRepository
{
    /** @var array<int, Restaurant> */
    private array $restaurants = [];

    public function __construct(
        private readonly InMemoryProductRepository $products,
        private readonly InMemoryPromotionRepository $promotions,
    ) {}

    public function addRestaurant(Restaurant $restaurant): void
    {
        $this->restaurants[$restaurant->id()] = $restaurant;
    }

    public function find(int $id): ?Restaurant
    {
        return $this->restaurants[$id] ?? null;
    }

    public function itemsAt(int $restaurantId, LocalMoment $moment): array
    {
        $visible = array_filter(
            $this->products->items,
            static fn(Product $p): bool => $p->belongsTo($restaurantId) && $p->isVisible() && $p->isAvailableAt($moment),
        );
        $order = array_flip(array_map(static fn(Category $c): string => $c->value, Category::cases()));
        usort($visible, static fn(Product $a, Product $b): int => [$order[$a->category()->value], $a->position(), $a->name()]
            <=> [$order[$b->category()->value], $b->position(), $b->name()]);

        $items = [];
        foreach ($visible as $product) {
            $item = new MenuItem($product->id(), $product->name(), $product->category()->value, $product->price()->cents());
            foreach ($this->promotions->ofProduct($product->id()) as $promotion) {
                $window = $promotion->activeWindowAt($moment);
                if ($window !== null) {
                    $item = new MenuItem(
                        $item->id,
                        $item->name,
                        $item->category,
                        $item->price,
                        $promotion->description(),
                        $promotion->price()->cents(),
                        $window->end()->format(),
                    );
                    break;
                }
            }
            $items[] = $item;
        }

        return $items;
    }
}
