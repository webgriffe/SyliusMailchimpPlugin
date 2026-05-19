<?php

declare(strict_types=1);

namespace Tests\Webgriffe\SyliusMailchimpPlugin\Unit\Mapper;

use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\ChannelPricing;
use Sylius\Component\Core\Model\Product;
use Sylius\Component\Core\Model\ProductTranslation;
use Sylius\Component\Core\Model\ProductVariant;
use Sylius\Component\Product\Model\ProductVariantTranslation;
use Tests\Webgriffe\SyliusMailchimpPlugin\Entity\Channel\Channel;
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
        $productTranslation = new ProductTranslation();
        $productTranslation->setLocale('en_US');
        $productTranslation->setName('T-Shirt');

        $product = new Product();
        $product->setCode('TSHIRT');
        $product->setCurrentLocale('en_US');
        $product->setFallbackLocale('en_US');
        $product->addTranslation($productTranslation);

        $channel = new Channel();
        $channel->setCode('WEB');

        $pricing = new ChannelPricing();
        $pricing->setChannelCode('WEB');
        $pricing->setPrice(2999);

        $variantTranslation = new ProductVariantTranslation();
        $variantTranslation->setLocale('en_US');

        $variant = new ProductVariant();
        $variant->setCode('TSHIRT-L');
        $variant->setCurrentLocale('en_US');
        $variant->setFallbackLocale('en_US');
        $variant->addTranslation($variantTranslation);
        $variant->addChannelPricing($pricing);
        $product->addVariant($variant);

        $pv = $this->mapper->map($variant, $channel, 'https://example.com/products/tshirt');

        $this->assertSame('TSHIRT-L', $pv->id);
        $this->assertSame('T-Shirt (TSHIRT-L)', $pv->title);
        $this->assertSame('https://example.com/products/tshirt', $pv->url);
        $this->assertSame('TSHIRT-L', $pv->sku);
        $this->assertSame(29.99, $pv->price);
    }

    public function testFallsBackToZeroPriceWhenNoPricing(): void
    {
        $productTranslation = new ProductTranslation();
        $productTranslation->setLocale('en_US');
        $productTranslation->setName('Product');

        $product = new Product();
        $product->setCode('PROD');
        $product->setCurrentLocale('en_US');
        $product->setFallbackLocale('en_US');
        $product->addTranslation($productTranslation);

        $channel = new Channel();
        $channel->setCode('WEB');

        $variantTranslation = new ProductVariantTranslation();
        $variantTranslation->setLocale('en_US');

        $variant = new ProductVariant();
        $variant->setCode('PROD-DEFAULT');
        $variant->setCurrentLocale('en_US');
        $variant->setFallbackLocale('en_US');
        $variant->addTranslation($variantTranslation);
        $product->addVariant($variant);

        $pv = $this->mapper->map($variant, $channel, 'https://example.com/products/prod');

        $this->assertSame(0.0, $pv->price);
    }

    public function testHandlesNullProductCode(): void
    {
        $productTranslation = new ProductTranslation();
        $productTranslation->setLocale('en_US');
        $productTranslation->setName('Unnamed Product');

        $product = new Product();
        $product->setCurrentLocale('en_US');
        $product->setFallbackLocale('en_US');
        $product->addTranslation($productTranslation);

        $channel = new Channel();
        $channel->setCode('WEB');

        $variantTranslation = new ProductVariantTranslation();
        $variantTranslation->setLocale('en_US');
        $variantTranslation->setName('Variant 1');

        $variant = new ProductVariant();
        $variant->setCode('VARIANT-1');
        $variant->setCurrentLocale('en_US');
        $variant->setFallbackLocale('en_US');
        $variant->addTranslation($variantTranslation);
        $product->addVariant($variant);

        $pv = $this->mapper->map($variant, $channel, 'https://example.com');

        $this->assertSame('VARIANT-1', $pv->id);
        $this->assertSame('VARIANT-1', $pv->sku);
    }
}
