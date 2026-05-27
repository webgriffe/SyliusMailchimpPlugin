<?php

declare(strict_types=1);

namespace Tests\Webgriffe\SyliusMailchimpPlugin\Unit\ValueObject;

use PHPUnit\Framework\TestCase;
use Webgriffe\SyliusMailchimpPlugin\ValueObject\Product;
use Webgriffe\SyliusMailchimpPlugin\ValueObject\ProductVariant;

final class ProductValueObjectTest extends TestCase
{
    public function test_product_stores_variants(): void
    {
        $variant = new ProductVariant('var-1', 'Red', 'https://example.com/red', 'SKU-001', 19.99);
        $product = new Product('prod-1', 'T-Shirt', 'https://example.com/tshirt', [$variant]);

        $this->assertSame('prod-1', $product->id);
        $this->assertCount(1, $product->variants);
        $this->assertSame('var-1', $product->variants[0]->id);
        $this->assertSame(19.99, $product->variants[0]->price);
    }
}
