<?php

declare(strict_types=1);

namespace Tests\Webgriffe\SyliusMailchimpPlugin\Unit\Mapper;

use Liip\ImagineBundle\Service\FilterService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\ChannelPricing;
use Sylius\Component\Core\Model\Product;
use Sylius\Component\Core\Model\ProductImage;
use Sylius\Component\Core\Model\ProductTranslation;
use Sylius\Component\Core\Model\ProductVariant;
use Sylius\Component\Product\Model\ProductVariantTranslation;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Tests\Webgriffe\SyliusMailchimpPlugin\Entity\Channel\Channel;
use Webgriffe\SyliusMailchimpPlugin\Mapper\ProductMapper;
use Webgriffe\SyliusMailchimpPlugin\Mapper\ProductVariantMapper;

final class ProductMapperTest extends TestCase
{
    private ProductMapper $mapper;

    /** @var UrlGeneratorInterface&MockObject */
    private UrlGeneratorInterface $router;

    /** @var FilterService&MockObject */
    private FilterService $imagineFilterService;

    protected function setUp(): void
    {
        $this->router = $this->createMock(UrlGeneratorInterface::class);
        $this->imagineFilterService = $this->createMock(FilterService::class);
        $this->mapper = new ProductMapper(new ProductVariantMapper(), $this->router, $this->imagineFilterService);
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

        $this->router
            ->expects($this->once())
            ->method('generate')
            ->with('sylius_shop_product_show', ['slug' => 'cool-tshirt', '_locale' => 'en_US'], UrlGeneratorInterface::ABSOLUTE_URL)
            ->willReturn('https://example.com/en_US/products/cool-tshirt');

        $mapped = $this->mapper->map($product, $channel, 'en_US');

        $this->assertSame('TSHIRT', $mapped->id);
        $this->assertSame('Cool T-Shirt', $mapped->title);
        $this->assertSame('https://example.com/en_US/products/cool-tshirt', $mapped->url);
        $this->assertSame('A cool t-shirt', $mapped->description);
        $this->assertCount(1, $mapped->variants);
        $this->assertSame('TSHIRT-L', $mapped->variants[0]->id);
    }

    public function testMapsProductImageUrl(): void
    {
        $channel = new Channel();
        $channel->setCode('WEB');
        $channel->setHostname('https://example.com');

        $translation = new ProductTranslation();
        $translation->setLocale('en_US');
        $translation->setSlug('cool-tshirt');
        $translation->setName('Cool T-Shirt');
        $translation->setDescription('');

        $image = new ProductImage();
        $image->setPath('product/ab/cd/image.jpg');

        $product = new Product();
        $product->setCode('TSHIRT');
        $product->setCurrentLocale('en_US');
        $product->setFallbackLocale('en_US');
        $product->addTranslation($translation);
        $product->addImage($image);

        $this->router->method('generate')->willReturn('https://example.com/en_US/products/cool-tshirt');
        $this->imagineFilterService
            ->expects($this->once())
            ->method('getUrlOfFilteredImage')
            ->with('product/ab/cd/image.jpg', 'sylius_shop_product_large_thumbnail')
            ->willReturn('https://example.com/media/cache/sylius_shop_product_large_thumbnail/product/ab/cd/image.webp');

        $mapped = $this->mapper->map($product, $channel, 'en_US');

        $this->assertSame('https://example.com/media/cache/sylius_shop_product_large_thumbnail/product/ab/cd/image.webp', $mapped->imageUrl);
    }

    public function testImageUrlIsEmptyWhenProductHasNoImages(): void
    {
        $channel = new Channel();
        $channel->setHostname('https://example.com');

        $translation = new ProductTranslation();
        $translation->setLocale('en_US');
        $translation->setSlug('cool-tshirt');
        $translation->setName('Product');
        $translation->setDescription('');

        $product = new Product();
        $product->setCode('PROD');
        $product->setCurrentLocale('en_US');
        $product->setFallbackLocale('en_US');
        $product->addTranslation($translation);

        $this->router->method('generate')->willReturn('https://example.com/en_US/products/cool-tshirt');
        $this->imagineFilterService->expects($this->never())->method('getUrlOfFilteredImage');

        $mapped = $this->mapper->map($product, $channel, 'en_US');

        $this->assertSame('', $mapped->imageUrl);
    }

    public function testFallsBackToHostnameWhenNoSlug(): void
    {
        $channel = new Channel();
        $channel->setHostname('https://example.com');

        $translation = new ProductTranslation();
        $translation->setLocale('en_US');
        $translation->setName('Product');
        $translation->setDescription('');

        $product = new Product();
        $product->setCode('PROD');
        $product->setCurrentLocale('en_US');
        $product->setFallbackLocale('en_US');
        $product->addTranslation($translation);

        $this->router->expects($this->never())->method('generate');

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

        $this->router->method('generate')->willReturn('https://example.com/en_US/products/product');

        $mapped = $this->mapper->map($product, $channel, 'en_US');

        $this->assertSame([], $mapped->variants);
    }
}
