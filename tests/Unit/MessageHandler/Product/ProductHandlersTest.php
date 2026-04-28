<?php

declare(strict_types=1);

namespace Tests\Webgriffe\SyliusMailchimpPlugin\Unit\MessageHandler\Product;

use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Sylius\Component\Channel\Repository\ChannelRepositoryInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Model\ProductTranslationInterface;
use Sylius\Component\Core\Repository\ProductRepositoryInterface;
use Webgriffe\SyliusMailchimpPlugin\Client\MailchimpClientInterface;
use Webgriffe\SyliusMailchimpPlugin\Mapper\ProductMapper;
use Webgriffe\SyliusMailchimpPlugin\Mapper\ProductVariantMapper;
use Webgriffe\SyliusMailchimpPlugin\Mapper\StoreMapper;
use Webgriffe\SyliusMailchimpPlugin\Message\Product\ProductCreate;
use Webgriffe\SyliusMailchimpPlugin\Message\Product\ProductRemove;
use Webgriffe\SyliusMailchimpPlugin\Message\Product\ProductUpdate;
use Webgriffe\SyliusMailchimpPlugin\MessageHandler\Product\ProductCreateHandler;
use Webgriffe\SyliusMailchimpPlugin\MessageHandler\Product\ProductRemoveHandler;
use Webgriffe\SyliusMailchimpPlugin\MessageHandler\Product\ProductUpdateHandler;
use Webgriffe\SyliusMailchimpPlugin\Model\ChannelMailchimpAwareInterface;

final class ProductHandlersTest extends TestCase
{
    private ProductRepositoryInterface $productRepository;

    private ChannelRepositoryInterface $channelRepository;

    private MailchimpClientInterface $mailchimpClient;

    private ProductMapper $productMapper;

    private StoreMapper $storeMapper;

    protected function setUp(): void
    {
        $this->productRepository = $this->createMock(ProductRepositoryInterface::class);
        $this->channelRepository = $this->createMock(ChannelRepositoryInterface::class);
        $this->mailchimpClient = $this->createMock(MailchimpClientInterface::class);
        $this->productMapper = new ProductMapper(new ProductVariantMapper());
        $this->storeMapper = new StoreMapper();
    }

    public function testProductCreateCallsUpsertProduct(): void
    {
        $product = $this->createProductMock();
        $channel = $this->createChannelMock('WEB');
        $this->productRepository->method('find')->with(10)->willReturn($product);
        $this->channelRepository->method('find')->with(1)->willReturn($channel);
        $this->mailchimpClient->expects($this->once())->method('upsertProduct');

        $handler = new ProductCreateHandler(
            $this->productRepository,
            $this->channelRepository,
            $this->productMapper,
            $this->storeMapper,
            $this->mailchimpClient,
            new NullLogger(),
        );
        $handler(new ProductCreate(10, 1, 'en_US'));
    }

    public function testProductCreateSkipsWhenProductNotFound(): void
    {
        $this->productRepository->method('find')->willReturn(null);
        $this->mailchimpClient->expects($this->never())->method('upsertProduct');

        $handler = new ProductCreateHandler(
            $this->productRepository,
            $this->channelRepository,
            $this->productMapper,
            $this->storeMapper,
            $this->mailchimpClient,
            new NullLogger(),
        );
        $handler(new ProductCreate(999, 1, 'en_US'));
    }

    public function testProductUpdateCallsUpsertProduct(): void
    {
        $product = $this->createProductMock();
        $channel = $this->createChannelMock('WEB');
        $this->productRepository->method('find')->willReturn($product);
        $this->channelRepository->method('find')->willReturn($channel);
        $this->mailchimpClient->expects($this->once())->method('upsertProduct');

        $handler = new ProductUpdateHandler(
            $this->productRepository,
            $this->channelRepository,
            $this->productMapper,
            $this->storeMapper,
            $this->mailchimpClient,
            new NullLogger(),
        );
        $handler(new ProductUpdate(10, 1, 'en_US'));
    }

    public function testProductRemoveCallsRemoveProduct(): void
    {
        $this->mailchimpClient->expects($this->once())->method('removeProduct')->with('WEB', 'TSHIRT');

        $handler = new ProductRemoveHandler($this->mailchimpClient, new NullLogger());
        $handler(new ProductRemove('WEB', 'TSHIRT'));
    }

    public function testProductRemoveRethrowsException(): void
    {
        $this->mailchimpClient->method('removeProduct')->willThrowException(new \RuntimeException('API error'));

        $handler = new ProductRemoveHandler($this->mailchimpClient, new NullLogger());

        $this->expectException(\RuntimeException::class);
        $handler(new ProductRemove('WEB', 'TSHIRT'));
    }

    private function createProductMock(): ProductInterface
    {
        $translation = $this->createMock(ProductTranslationInterface::class);
        $translation->method('getSlug')->willReturn('tshirt');
        $translation->method('getName')->willReturn('T-Shirt');
        $translation->method('getDescription')->willReturn('A t-shirt');

        $product = $this->createMock(ProductInterface::class);
        $product->method('getCode')->willReturn('TSHIRT');
        $product->method('getTranslation')->willReturn($translation);
        $product->method('getVariants')->willReturn(new ArrayCollection([]));

        return $product;
    }

    /** @return ChannelInterface&ChannelMailchimpAwareInterface */
    private function createChannelMock(string $code): ChannelInterface&ChannelMailchimpAwareInterface
    {
        /** @var ChannelInterface&ChannelMailchimpAwareInterface $channel */
        $channel = $this->createMockForIntersectionOfInterfaces([ChannelInterface::class, ChannelMailchimpAwareInterface::class]);
        $channel->method('getCode')->willReturn($code);
        $channel->method('getName')->willReturn('Test Store');
        $channel->method('getHostname')->willReturn('https://example.com');
        $channel->method('getContactEmail')->willReturn('test@example.com');
        $channel->method('getLocales')->willReturn(new ArrayCollection([]));
        $channel->method('getCurrencies')->willReturn(new ArrayCollection([]));

        return $channel;
    }
}
