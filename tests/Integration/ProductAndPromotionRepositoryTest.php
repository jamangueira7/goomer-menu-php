<?php

declare(strict_types=1);

namespace Goomer\Tests\Integration;

use Goomer\Product\Domain\Category;
use Goomer\Product\Domain\Product;
use Goomer\Product\Infrastructure\PdoProductRepository;
use Goomer\Promotion\Domain\OverlappingPromotion;
use Goomer\Promotion\Domain\Promotion;
use Goomer\Promotion\Infrastructure\PdoPromotionRepository;
use Goomer\Shared\Domain\Money;
use Goomer\Shared\Domain\TimeWindow;
use Goomer\Shared\Infrastructure\Database\Database;
use Goomer\Tests\Support\TestDatabase;
use PHPUnit\Framework\TestCase;

final class ProductAndPromotionRepositoryTest extends TestCase
{
    private Database $db;
    private PdoProductRepository $products;
    private PdoPromotionRepository $promotions;

    protected function setUp(): void
    {
        $this->db = TestDatabase::reset();
        $this->products = new PdoProductRepository($this->db);
        $this->promotions = new PdoPromotionRepository($this->db);
    }

    private function product(int $restaurant, string $name, Category $category = Category::Drinks, int $price = 1400): Product
    {
        $product = Product::create(
            $this->products->nextId(),
            $restaurant,
            $name,
            Money::ofCents($price),
            $category,
            true,
            $this->products->lastPosition($restaurant) + 1,
            [TimeWindow::fromStrings(6, '11:00', '16:00')],
        );
        $this->products->save($product);

        return $product;
    }

    public function test_saves_and_restores_product_with_availability(): void
    {
        $saved = $this->product(1, 'Feijoada', Category::MainCourses);

        $found = $this->products->find(1, $saved->id());
        self::assertNotNull($found);
        self::assertSame('Feijoada', $found->name());
        self::assertSame(Category::MainCourses, $found->category());
        self::assertSame('16:00', $found->availability()[0]->end()->format());
        self::assertNull($this->products->find(2, $saved->id()), 'outro restaurante não enxerga');

        $found->update('Feijoada completa', Money::ofCents(7000), Category::MainCourses, false, []);
        $this->products->save($found);
        $again = $this->products->find(1, $saved->id());
        self::assertNotNull($again);
        self::assertSame([], $again->availability());
        self::assertFalse($again->isVisible());
    }

    public function test_search_filters_paginates_and_reorders(): void
    {
        $a = $this->product(1, 'A');
        $b = $this->product(1, 'B', Category::Desserts);
        $c = $this->product(1, 'C');
        $this->product(2, 'Outro');

        self::assertSame(3, $this->products->search(1, null, null, 1, 10)['total']);
        self::assertSame(1, $this->products->search(1, Category::Desserts, true, 1, 10)['total']);
        self::assertSame(0, $this->products->search(1, null, false, 1, 10)['total']);
        $page = $this->products->search(1, null, null, 2, 2);
        self::assertSame('C', $page['items'][0]->name());

        $this->products->savePositions(1, [$c->id() => 1, $a->id() => 2, $b->id() => 3]);
        self::assertSame([$c->id(), $a->id(), $b->id()], $this->products->idsOf(1));
        self::assertSame(3, $this->products->lastPosition(1));
    }

    public function test_promotion_round_trip_and_cascade_on_product_delete(): void
    {
        $chopp = $this->product(1, 'Chopp');
        $promotion = Promotion::create($this->promotions->nextId(), $chopp->id(), $chopp->price(), 'Happy hour', Money::ofCents(700), [
            TimeWindow::fromStrings(3, '18:00', '20:00'),
            TimeWindow::fromStrings(4, '18:00', '20:00'),
        ]);
        $this->promotions->save($promotion);

        $found = $this->promotions->find(1, $promotion->id());
        self::assertNotNull($found);
        self::assertCount(2, $found->windows());
        self::assertNull($this->promotions->find(2, $promotion->id()));
        self::assertCount(1, $this->promotions->ofRestaurant(1, $chopp->id()));
        self::assertCount(0, $this->promotions->ofRestaurant(1, 999));
        self::assertCount(1, $this->promotions->ofProduct($chopp->id()));

        $this->products->delete(1, $chopp->id());
        self::assertSame([], $this->promotions->ofProduct($chopp->id()));
        self::assertSame(0, $this->db->fetchInt('SELECT count(*) FROM promotion_windows'));
    }

    public function test_database_excludes_overlapping_windows_of_same_product(): void
    {
        $chopp = $this->product(1, 'Chopp');
        $first = Promotion::create($this->promotions->nextId(), $chopp->id(), $chopp->price(), 'Primeira', Money::ofCents(700), [
            TimeWindow::fromStrings(3, '18:00', '20:00'),
        ]);
        $this->promotions->save($first);

        // Sem a checagem do domínio: a restrição EXCLUDE USING gist é a segunda linha de defesa.
        $second = Promotion::create($this->promotions->nextId(), $chopp->id(), $chopp->price(), 'Segunda', Money::ofCents(800), [
            TimeWindow::fromStrings(3, '19:45', '21:00'),
        ]);
        try {
            $this->promotions->save($second);
            self::fail('O banco deveria recusar a sobreposição');
        } catch (OverlappingPromotion) {
            self::assertCount(1, $this->promotions->ofProduct($chopp->id()), 'a transação foi desfeita');
        }

        $adjacent = Promotion::create($this->promotions->nextId(), $chopp->id(), $chopp->price(), 'Encostada', Money::ofCents(800), [
            TimeWindow::fromStrings(3, '20:00', '21:00'),
        ]);
        $this->promotions->save($adjacent);
        self::assertCount(2, $this->promotions->ofProduct($chopp->id()));

        $this->promotions->delete($adjacent->id());
        self::assertCount(1, $this->promotions->ofProduct($chopp->id()));
    }

    public function test_check_constraints_guard_times_and_prices(): void
    {
        $chopp = $this->product(1, 'Chopp');
        $invalid = [
            "INSERT INTO product_availability VALUES ({$chopp->id()}, 1, '10:10', '11:00')",
            "INSERT INTO product_availability VALUES ({$chopp->id()}, 1, '10:00', '10:00')",
            "INSERT INTO product_availability VALUES ({$chopp->id()}, 7, '10:00', '11:00')",
            "INSERT INTO products (restaurant_id, name, price_cents, category) VALUES (1, 'Grátis', 0, 'bebidas')",
            "INSERT INTO products (restaurant_id, name, price_cents, category) VALUES (1, 'X', 100, 'lanches')",
        ];
        foreach ($invalid as $sql) {
            try {
                $this->db->execute($sql);
                self::fail("Deveria falhar: {$sql}");
            } catch (\PDOException) {
                self::addToAssertionCount(1);
            }
        }
    }
}
