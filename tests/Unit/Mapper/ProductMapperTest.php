<?php

declare(strict_types=1);

namespace Tests\Webgriffe\SyliusMailchimpPlugin\Unit\Mapper;

use Liip\ImagineBundle\Service\FilterService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\Product;
use Sylius\Component\Core\Model\ProductImage;
use Sylius\Component\Core\Model\ProductTranslation;
use Sylius\Component\Core\Model\ProductVariant;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Tests\Webgriffe\SyliusMailchimpPlugin\Entity\Channel\Channel;
use Webgriffe\SyliusMailchimpPlugin\Mapper\ProductMapper;
use Webgriffe\SyliusMailchimpPlugin\Mapper\ProductVariantMapperInterface;

final class ProductMapperTest extends TestCase
{
    private ProductMapper $mapper;

    /** @var UrlGeneratorInterface&MockObject */
    private UrlGeneratorInterface $router;

    /** @var FilterService&MockObject */
    private FilterService $imagineFilterService;

    /** @var ProductVariantMapperInterface&MockObject */
    private ProductVariantMapperInterface $productVariantMapper;

    protected function setUp(): void
    {
        $this->router = $this->createMock(UrlGeneratorInterface::class);
        $this->imagineFilterService = $this->createMock(FilterService::class);
        $this->productVariantMapper = $this->createMock(ProductVariantMapperInterface::class);
        $this->mapper = new ProductMapper($this->productVariantMapper, $this->router, $this->imagineFilterService);
    }

    public function test_maps_product_with_variants(): void
    {
        $channel = new Channel();
        $channel->setCode('WEB');
        $channel->setHostname('https://example.com');

        $translation = new ProductTranslation();
        $translation->setLocale('en_US');
        $translation->setSlug('cool-tshirt');
        $translation->setName('Cool T-Shirt');
        $translation->setDescription('A cool t-shirt');

        $variant = new ProductVariant();
        $variant->setCode('TSHIRT-L');
        $variant->setCurrentLocale('en_US');
        $variant->setFallbackLocale('en_US');

        $product = new Product();
        $product->setCode('TSHIRT');
        $product->setCurrentLocale('en_US');
        $product->setFallbackLocale('en_US');
        $product->addTranslation($translation);
        $product->addVariant($variant);

        $this->router
            ->expects($this->once())
            ->method('generate')
            ->with('sylius_shop_product_show', ['slug' => 'cool-tshirt', '_locale' => 'en_US'], UrlGeneratorInterface::ABSOLUTE_URL)
            ->willReturn('https://example.com/en_US/products/cool-tshirt');

        $mappedVariant = ['id' => 'TSHIRT-L', 'title' => 'Cool T-Shirt (TSHIRT-L)', 'url' => 'https://example.com/en_US/products/cool-tshirt', 'sku' => 'TSHIRT-L', 'price' => 0.0, 'inventory_quantity' => 0];
        $this->productVariantMapper->method('map')->willReturn($mappedVariant);

        $mapped = $this->mapper->map($product, $channel, 'en_US');

        $this->assertSame('TSHIRT', $mapped['id']);
        $this->assertSame('Cool T-Shirt', $mapped['title']);
        $this->assertSame('https://example.com/en_US/products/cool-tshirt', $mapped['url']);
        $this->assertSame('A cool t-shirt', $mapped['description']);
        $this->assertCount(1, $mapped['variants']);
        $this->assertSame('TSHIRT-L', $mapped['variants'][0]['id']);
    }

    public function test_maps_product_image_url(): void
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

        $this->assertSame('https://example.com/media/cache/sylius_shop_product_large_thumbnail/product/ab/cd/image.webp', $mapped['image_url']);
    }

    public function test_image_url_is_empty_when_product_has_no_images(): void
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

        $this->assertArrayNotHasKey('image_url', $mapped);
    }

    public function test_falls_back_to_hostname_when_no_slug(): void
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

        $this->assertSame('https://example.com', $mapped['url']);
    }

    public function test_maps_product_with_no_variants(): void
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

        $this->assertSame([], $mapped['variants']);
    }
}
