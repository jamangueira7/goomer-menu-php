<?php

declare(strict_types=1);

namespace Goomer\Tests\Unit\Product;

use Goomer\Product\Domain\Category;
use Goomer\Product\Domain\Product;
use Goomer\Shared\Domain\Exception\ValidationError;
use Goomer\Shared\Domain\Money;
use Goomer\Shared\Domain\TimeWindow;
use Goomer\Shared\Domain\Timezone;
use PHPUnit\Framework\TestCase;

final class ProductTest extends TestCase
{
    public function test_creates_product_with_trimmed_name(): void
    {
        $product = Product::create(1, 10, '  Chopp 300 ml ', Money::ofCents(1400), Category::Drinks, true, 1);

        self::assertSame('Chopp 300 ml', $product->name());
        self::assertSame(1400, $product->price()->cents());
        self::assertSame(Category::Drinks, $product->category());
        self::assertTrue($product->isVisible());
        self::assertSame(1, $product->position());
        self::assertSame(10, $product->restaurantId());
        self::assertTrue($product->belongsTo(10));
        self::assertFalse($product->belongsTo(11));
    }

    public function test_rejects_empty_name_long_name_and_zero_price(): void
    {
        $errors = 0;
        foreach ([
            static fn() => Product::create(1, 1, '   ', Money::ofCents(100), Category::Drinks, true, 1),
            static fn() => Product::create(1, 1, str_repeat('a', 121), Money::ofCents(100), Category::Drinks, true, 1),
            static fn() => Product::create(1, 1, 'Água', Money::ofCents(0), Category::Drinks, true, 1),
            static fn() => Product::create(1, 1, 'Água', Money::ofCents(100), Category::Drinks, true, 1, [
                TimeWindow::fromStrings(1, '10:00', '12:00'),
                TimeWindow::fromStrings(1, '11:00', '13:00'),
            ]),
        ] as $attempt) {
            try {
                $attempt();
            } catch (ValidationError) {
                ++$errors;
            }
        }

        self::assertSame(4, $errors);
    }

    public function test_visibility_and_position(): void
    {
        $product = Product::create(1, 1, 'Pudim', Money::ofCents(1800), Category::Desserts, true, 1);
        $product->hide();
        self::assertFalse($product->isVisible());
        $product->show();
        self::assertTrue($product->isVisible());
        $product->moveTo(5);
        self::assertSame(5, $product->position());
    }

    public function test_without_windows_is_always_available(): void
    {
        $tz = Timezone::fromString('America/Sao_Paulo');
        $always = Product::create(1, 1, 'Pudim', Money::ofCents(1800), Category::Desserts, true, 1);
        $saturdayLunch = Product::create(2, 1, 'Feijoada', Money::ofCents(6500), Category::MainCourses, true, 2, [
            TimeWindow::fromStrings(6, '11:00', '16:00'),
        ]);

        $wednesdayEvening = $tz->localize(new \DateTimeImmutable('2026-12-02T21:30:00Z'));
        $saturdayNoon = $tz->localize(new \DateTimeImmutable('2026-12-05T15:00:00Z'));

        self::assertTrue($always->isAvailableAt($wednesdayEvening));
        self::assertFalse($saturdayLunch->isAvailableAt($wednesdayEvening));
        self::assertTrue($saturdayLunch->isAvailableAt($saturdayNoon));
        self::assertCount(1, $saturdayLunch->availability());
    }

    public function test_category_parsing_and_labels(): void
    {
        self::assertSame(Category::MainCourses, Category::fromString('pratos_principais'));
        self::assertSame('Entradas', Category::Starters->label());
        self::assertSame('Pratos principais', Category::MainCourses->label());
        self::assertSame('Sobremesas', Category::Desserts->label());
        self::assertSame('Bebidas', Category::Drinks->label());

        $this->expectException(ValidationError::class);
        Category::fromString('lanches');
    }
}
