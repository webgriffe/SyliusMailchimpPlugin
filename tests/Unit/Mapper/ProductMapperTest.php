<?php

declare(strict_types=1);

namespace Tests\Webgriffe\SyliusMailchimpPlugin\Unit\Mapper;

use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\ChannelPricingInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Model\ProductTranslationInterface;
use Sylius\Component\Core\Model\ProductVariantInterface;
use Webgriffe\SyliusMailchimpPlugin\Mapper\ProductMapper;
use Webgriffe\SyliusMailchimpPlugin\Mapper\ProductVariantMapper;

final class ProductMapperTest extends TestCase
{
    private ProductMapper $mapper;

    protected function setUp(): void
    {
        $this->mapper = new ProductMapper(new ProductVariantMapper());
    }

    public function testMapsProductWithVariants(): void
    {
        $channel = $this->createMock(ChannelInterface::class);
        $channel->method('getHostname')->willReturn('https://example.com');

        $translation = $this->createMock(ProductTranslationInterface::class);
        $translation->method('getSlug')->willReturn('cool-tshirt');
        $translation->method('getName')->willReturn('Cool T-Shirt');
        $translation->method('getDescription')->willReturn('A cool t-shirt');

        $pricing = $this->createMock(ChannelPricingInterface::class);
        $pricing->method('getPrice')->willReturn(1999);

        $product = $this->createMock(ProductInterface::class);
        $product->method('getCode')->willReturn('TSHIRT');

        $variant = $this->createMock(ProductVariantInterface::class);
        $variant->method('getProduct')->willReturn($product);
        $variant->method('getCode')->willReturn('TSHIRT-L');
        $variant->method('getDescriptor')->willReturn('T-Shirt L');
        $variant->method('getChannelPricingForChannel')->willReturn($pricing);

        $product->method('getTranslation')->with('en_US')->willReturn($translation);
        $product->method('getVariants')->willReturn(new ArrayCollection([$variant]));

        $mapped = $this->mapper->map($product, $channel, 'en_US');

        $this->assertSame('TSHIRT', $mapped->id);
        $this->assertSame('Cool T-Shirt', $mapped->title);
        $this->assertSame('https://example.com/products/cool-tshirt', $mapped->url);
        $this->assertSame('A cool t-shirt', $mapped->description);
        $this->assertCount(1, $mapped->variants);
        $this->assertSame('TSHIRT-L', $mapped->variants[0]->id);
    }

    public function testFallsBackToHostnameWhenNoSlug(): void
    {
        $channel = $this->createMock(ChannelInterface::class);
        $channel->method('getHostname')->willReturn('https://example.com/');

        $translation = $this->createMock(ProductTranslationInterface::class);
        $translation->method('getSlug')->willReturn(null);
        $translation->method('getName')->willReturn('Product');
        $translation->method('getDescription')->willReturn('');

        $product = $this->createMock(ProductInterface::class);
        $product->method('getCode')->willReturn('PROD');
        $product->method('getTranslation')->willReturn($translation);
        $product->method('getVariants')->willReturn(new ArrayCollection([]));

        $mapped = $this->mapper->map($product, $channel, 'en_US');

        $this->assertSame('https://example.com', $mapped->url);
    }

    public function testMapsProductWithNoVariants(): void
    {
        $channel = $this->createMock(ChannelInterface::class);
        $channel->method('getHostname')->willReturn('https://example.com');

        $translation = $this->createMock(ProductTranslationInterface::class);
        $translation->method('getSlug')->willReturn('product');
        $translation->method('getName')->willReturn('Product');
        $translation->method('getDescription')->willReturn('');

        $product = $this->createMock(ProductInterface::class);
        $product->method('getCode')->willReturn('PROD');
        $product->method('getTranslation')->willReturn($translation);
        $product->method('getVariants')->willReturn(new ArrayCollection([]));

        $mapped = $this->mapper->map($product, $channel, 'en_US');

        $this->assertSame([], $mapped->variants);
    }
}
