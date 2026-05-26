<?php

declare(strict_types=1);

namespace Tests\Webgriffe\SyliusMailchimpPlugin\Unit\Mapper;

use Liip\ImagineBundle\Service\FilterService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Calculator\ProductVariantPricesCalculatorInterface;
use Sylius\Component\Core\Model\Product;
use Sylius\Component\Core\Model\ProductImage;
use Sylius\Component\Core\Model\ProductTranslation;
use Sylius\Component\Core\Model\ProductVariant;
use Sylius\Component\Product\Model\ProductVariantTranslation;
use Tests\Webgriffe\SyliusMailchimpPlugin\Entity\Channel\Channel;
use Webgriffe\SyliusMailchimpPlugin\Mapper\ProductVariantMapper;

final class ProductVariantMapperTest extends TestCase
{
    private ProductVariantMapper $mapper;

    /** @var ProductVariantPricesCalculatorInterface&MockObject */
    private ProductVariantPricesCalculatorInterface $pricesCalculator;

    /** @var FilterService&MockObject */
    private FilterService $imagineFilterService;

    protected function setUp(): void
    {
        $this->pricesCalculator = $this->createMock(ProductVariantPricesCalculatorInterface::class);
        $this->imagineFilterService = $this->createMock(FilterService::class);
        $this->mapper = new ProductVariantMapper($this->pricesCalculator, $this->imagineFilterService);
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

        $variantTranslation = new ProductVariantTranslation();
        $variantTranslation->setLocale('en_US');

        $variant = new ProductVariant();
        $variant->setCode('TSHIRT-L');
        $variant->setCurrentLocale('en_US');
        $variant->setFallbackLocale('en_US');
        $variant->addTranslation($variantTranslation);
        $product->addVariant($variant);

        $this->pricesCalculator->method('calculate')->willReturn(2999);

        $pv = $this->mapper->map($variant, $channel, 'https://example.com/en_US/products/tshirt');

        $this->assertSame('TSHIRT-L', $pv->id);
        $this->assertSame('T-Shirt (TSHIRT-L)', $pv->title);
        $this->assertSame('https://example.com/en_US/products/tshirt', $pv->url);
        $this->assertSame('TSHIRT-L', $pv->sku);
        $this->assertSame(29.99, $pv->price);
    }

    public function testMapsInventoryQuantityWhenTracked(): void
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

        $variant = new ProductVariant();
        $variant->setCode('TSHIRT-L');
        $variant->setCurrentLocale('en_US');
        $variant->setFallbackLocale('en_US');
        $variant->setTracked(true);
        $variant->setOnHand(10);
        $variant->setOnHold(3);
        $product->addVariant($variant);

        $this->pricesCalculator->method('calculate')->willReturn(0);

        $pv = $this->mapper->map($variant, $channel, 'https://example.com');

        $this->assertSame(7, $pv->inventoryQuantity);
    }

    public function testInventoryQuantityIsZeroWhenNotTracked(): void
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

        $variant = new ProductVariant();
        $variant->setCode('TSHIRT-L');
        $variant->setCurrentLocale('en_US');
        $variant->setFallbackLocale('en_US');
        $variant->setTracked(false);
        $variant->setOnHand(10);
        $product->addVariant($variant);

        $this->pricesCalculator->method('calculate')->willReturn(0);

        $pv = $this->mapper->map($variant, $channel, 'https://example.com');

        $this->assertSame(0, $pv->inventoryQuantity);
    }

    public function testMapsVariantImageUrl(): void
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

        $image = new ProductImage();
        $image->setPath('product/ab/cd/variant-image.jpg');

        $variant = new ProductVariant();
        $variant->setCode('TSHIRT-L');
        $variant->setCurrentLocale('en_US');
        $variant->setFallbackLocale('en_US');
        $variant->addImage($image);
        $product->addVariant($variant);

        $this->pricesCalculator->method('calculate')->willReturn(0);
        $this->imagineFilterService
            ->expects($this->once())
            ->method('getUrlOfFilteredImage')
            ->with('product/ab/cd/variant-image.jpg', 'sylius_shop_product_large_thumbnail')
            ->willReturn('https://example.com/media/cache/sylius_shop_product_large_thumbnail/product/ab/cd/variant-image.webp');

        $pv = $this->mapper->map($variant, $channel, 'https://example.com');

        $this->assertSame('https://example.com/media/cache/sylius_shop_product_large_thumbnail/product/ab/cd/variant-image.webp', $pv->imageUrl);
    }

    public function testImageUrlIsEmptyWhenVariantHasNoImages(): void
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

        $variant = new ProductVariant();
        $variant->setCode('TSHIRT-L');
        $variant->setCurrentLocale('en_US');
        $variant->setFallbackLocale('en_US');
        $product->addVariant($variant);

        $this->pricesCalculator->method('calculate')->willReturn(0);
        $this->imagineFilterService->expects($this->never())->method('getUrlOfFilteredImage');

        $pv = $this->mapper->map($variant, $channel, 'https://example.com');

        $this->assertSame('', $pv->imageUrl);
    }
}
