<?php

declare(strict_types=1);

namespace Goomer\Tests\UseCase;

use Goomer\Product\Application\ChangeProductVisibilityUseCase;
use Goomer\Product\Application\CreateProductUseCase;
use Goomer\Product\Application\DeleteProductUseCase;
use Goomer\Product\Application\GetProductUseCase;
use Goomer\Product\Application\ListProductsUseCase;
use Goomer\Product\Application\ProductData;
use Goomer\Product\Application\ReorderProductsUseCase;
use Goomer\Product\Application\UpdateProductUseCase;
use Goomer\Product\Domain\ProductNotFound;
use Goomer\Shared\Domain\Exception\ValidationError;
use Goomer\Tests\Support\InMemoryProductRepository;
use PHPUnit\Framework\TestCase;

final class ProductUseCasesTest extends TestCase
{
    private InMemoryProductRepository $products;

    protected function setUp(): void
    {
        $this->products = new InMemoryProductRepository();
    }

    private function create(int $restaurant, string $name, string $category = 'bebidas', bool $visible = true): int
    {
        return (new CreateProductUseCase($this->products))
            ->execute($restaurant, new ProductData($name, 1000, $category, $visible))->id;
    }

    public function test_create_assigns_next_position_and_returns_output(): void
    {
        $useCase = new CreateProductUseCase($this->products);
        $first = $useCase->execute(1, new ProductData('Chopp', 1400, 'bebidas', true, [
            ['weekday' => 3, 'start' => '18:00', 'end' => '20:00'],
        ]));
        $second = $useCase->execute(1, new ProductData('Água', 500, 'bebidas'));

        self::assertSame(1, $first->position);
        self::assertSame(2, $second->position);
        self::assertSame([['weekday' => 3, 'start' => '18:00', 'end' => '20:00']], $first->availability);
        self::assertSame('bebidas', $first->toArray()['category']);
    }

    public function test_create_rejects_invalid_category_and_window(): void
    {
        $useCase = new CreateProductUseCase($this->products);
        $this->expectException(ValidationError::class);
        $useCase->execute(1, new ProductData('Chopp', 1400, 'bebidas', true, [
            ['weekday' => 3, 'start' => '18:10', 'end' => '20:00'],
        ]));
    }

    public function test_update_get_and_visibility_respect_restaurant(): void
    {
        $id = $this->create(1, 'Chopp');

        $updated = (new UpdateProductUseCase($this->products))->execute(1, $id, new ProductData('Chopp 500 ml', 1900, 'bebidas'));
        self::assertSame('Chopp 500 ml', $updated->name);
        self::assertSame(1900, (new GetProductUseCase($this->products))->execute(1, $id)->price);

        $hidden = (new ChangeProductVisibilityUseCase($this->products))->execute(1, $id, false);
        self::assertFalse($hidden->visible);
        self::assertTrue((new ChangeProductVisibilityUseCase($this->products))->execute(1, $id, true)->visible);

        $this->expectException(ProductNotFound::class);
        (new GetProductUseCase($this->products))->execute(2, $id);
    }

    public function test_update_of_other_restaurant_is_not_found(): void
    {
        $id = $this->create(1, 'Chopp');
        $this->expectException(ProductNotFound::class);
        (new UpdateProductUseCase($this->products))->execute(2, $id, new ProductData('X', 100, 'bebidas'));
    }

    public function test_list_filters_and_paginates(): void
    {
        $this->create(1, 'Chopp');
        $this->create(1, 'Pudim', 'sobremesas');
        $this->create(1, 'Secreto', 'sobremesas', false);
        $this->create(2, 'De outro restaurante');

        $list = new ListProductsUseCase($this->products);
        self::assertSame(3, $list->execute(1, null, null)->total);
        self::assertSame(2, $list->execute(1, 'sobremesas', null)->total);
        self::assertSame(1, $list->execute(1, 'sobremesas', false)->total);

        $page = $list->execute(1, null, null, 2, 2);
        self::assertSame(3, $page->total);
        self::assertCount(1, $page->items);
        self::assertSame('Secreto', $page->items[0]->name);

        $this->expectException(ValidationError::class);
        $list->execute(1, null, null, 1, 101);
    }

    public function test_reorder_puts_listed_ids_first(): void
    {
        $a = $this->create(1, 'A');
        $b = $this->create(1, 'B');
        $c = $this->create(1, 'C');

        (new ReorderProductsUseCase($this->products))->execute(1, [$c, $a]);

        self::assertSame([$c, $a, $b], $this->products->idsOf(1));
    }

    public function test_reorder_validates_ids(): void
    {
        $a = $this->create(1, 'A');
        $other = $this->create(2, 'De outro');
        $reorder = new ReorderProductsUseCase($this->products);

        foreach ([[], [$a, $a], [$a, $other]] as $ids) {
            try {
                $reorder->execute(1, $ids);
                self::fail('Era para recusar ' . json_encode($ids));
            } catch (ValidationError $e) {
                self::assertSame('ids', $e->field());
            }
        }
    }

    public function test_delete(): void
    {
        $id = $this->create(1, 'Chopp');
        $delete = new DeleteProductUseCase($this->products);
        $delete->execute(1, $id);
        self::assertSame([], $this->products->idsOf(1));

        $this->expectException(ProductNotFound::class);
        $delete->execute(1, $id);
    }
}
