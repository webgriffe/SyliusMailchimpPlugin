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
        $channel = new Channel();
        $channel->setCode('WEB');
        $channel->setHostname('https://example.com');

        $pricing = new ChannelPricing();
        $pricing->setChannelCode('WEB');
        $pricing->setPrice(1999);

        $translation = new ProductTranslation();
        $translation->setLocale('en_US');
        $translation->setSlug('cool-tshirt');
        $translation->setName('Cool T-Shirt');
        $translation->setDescription('A cool t-shirt');

        $product = new Product();
        $product->setCode('TSHIRT');
        $product->setCurrentLocale('en_US');
        $product->setFallbackLocale('en_US');
        $product->addTranslation($translation);

        $variantTranslation = new ProductVariantTranslation();
        $variantTranslation->setLocale('en_US');

        $variant = new ProductVariant();
        $variant->setCode('TSHIRT-L');
        $variant->setCurrentLocale('en_US');
        $variant->setFallbackLocale('en_US');
        $variant->addTranslation($variantTranslation);
        $variant->addChannelPricing($pricing);
        $product->addVariant($variant);

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
        $channel = new Channel();
        $channel->setHostname('https://example.com/');

        $translation = new ProductTranslation();
        $translation->setLocale('en_US');
        $translation->setName('Product');
        $translation->setDescription('');

        $product = new Product();
        $product->setCode('PROD');
        $product->setCurrentLocale('en_US');
        $product->setFallbackLocale('en_US');
        $product->addTranslation($translation);

        $mapped = $this->mapper->map($product, $channel, 'en_US');

        $this->assertSame('https://example.com', $mapped->url);
    }

    public function testMapsProductWithNoVariants(): void
    {
        $channel = new Channel();
        $channel->setHostname('https://example.com');

        $translation = new ProductTranslation();
        $translation->setLocale('en_US');
        $translation->setSlug('product');
        $translation->setName('Product');
        $translation->setDescription('');

        $product = new Product();
        $product->setCode('PROD');
        $product->setCurrentLocale('en_US');
        $product->setFallbackLocale('en_US');
        $product->addTranslation($translation);

        $mapped = $this->mapper->map($product, $channel, 'en_US');

        $this->assertSame([], $mapped->variants);
    }
}
