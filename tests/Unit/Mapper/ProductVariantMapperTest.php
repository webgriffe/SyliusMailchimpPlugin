<?php

declare(strict_types=1);

namespace Tests\Webgriffe\SyliusMailchimpPlugin\Unit\Mapper;

use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\ChannelPricingInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Model\ProductVariantInterface;
use Webgriffe\SyliusMailchimpPlugin\Mapper\ProductVariantMapper;

final class ProductVariantMapperTest extends TestCase
{
    private ProductVariantMapper $mapper;

    protected function setUp(): void
    {
        $this->mapper = new ProductVariantMapper();
    }

    public function testMapsVariantWithPricing(): void
    {
        $product = $this->createMock(ProductInterface::class);
        $product->method('getCode')->willReturn('TSHIRT');

        $pricing = $this->createMock(ChannelPricingInterface::class);
        $pricing->method('getPrice')->willReturn(2999);

        $channel = $this->createMock(ChannelInterface::class);

        $variant = $this->createMock(ProductVariantInterface::class);
        $variant->method('getProduct')->willReturn($product);
        $variant->method('getCode')->willReturn('TSHIRT-L');
        $variant->method('getDescriptor')->willReturn('T-Shirt - L');
        $variant->method('getChannelPricingForChannel')->with($channel)->willReturn($pricing);

        $pv = $this->mapper->map($variant, $channel, 'https://example.com/products/tshirt');

        $this->assertSame('TSHIRT-L', $pv->id);
        $this->assertSame('T-Shirt - L', $pv->title);
        $this->assertSame('https://example.com/products/tshirt', $pv->url);
        $this->assertSame('TSHIRT-L', $pv->sku);
        $this->assertSame(29.99, $pv->price);
    }

    public function testFallsBackToZeroPriceWhenNoPricing(): void
    {
        $product = $this->createMock(ProductInterface::class);
        $product->method('getCode')->willReturn('PROD');

        $channel = $this->createMock(ChannelInterface::class);

        $variant = $this->createMock(ProductVariantInterface::class);
        $variant->method('getProduct')->willReturn($product);
        $variant->method('getCode')->willReturn('PROD-DEFAULT');
        $variant->method('getDescriptor')->willReturn('Product');
        $variant->method('getChannelPricingForChannel')->with($channel)->willReturn(null);

        $pv = $this->mapper->map($variant, $channel, 'https://example.com/products/prod');

        $this->assertSame(0.0, $pv->price);
    }

    public function testHandlesNullProductCode(): void
    {
        $product = $this->createMock(ProductInterface::class);
        $product->method('getCode')->willReturn(null);

        $channel = $this->createMock(ChannelInterface::class);

        $variant = $this->createMock(ProductVariantInterface::class);
        $variant->method('getProduct')->willReturn($product);
        $variant->method('getCode')->willReturn('VARIANT-1');
        $variant->method('getDescriptor')->willReturn('Variant 1');
        $variant->method('getChannelPricingForChannel')->willReturn(null);

        $pv = $this->mapper->map($variant, $channel, 'https://example.com');

        $this->assertSame('VARIANT-1', $pv->id);
        $this->assertSame('VARIANT-1', $pv->sku);
    }
}
