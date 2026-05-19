<?php

declare(strict_types=1);

namespace Tests\Webgriffe\SyliusMailchimpPlugin\Unit\MessageHandler\Product;

use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Sylius\Component\Channel\Repository\ChannelRepositoryInterface;
use Sylius\Component\Core\Model\Product;
use Sylius\Component\Core\Model\ProductTranslation;
use Sylius\Component\Core\Repository\ProductRepositoryInterface;
use Tests\Webgriffe\SyliusMailchimpPlugin\Entity\Channel\Channel;
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
        $product = $this->createProduct();
        $channel = $this->createChannel('WEB');
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
        $product = $this->createProduct();
        $channel = $this->createChannel('WEB');
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

    private function createProduct(): Product
    {
        $translation = new ProductTranslation();
        $translation->setLocale('en_US');
        $translation->setSlug('tshirt');
        $translation->setName('T-Shirt');
        $translation->setDescription('A t-shirt');

        $product = new Product();
        $product->setCode('TSHIRT');
        $product->setCurrentLocale('en_US');
        $product->setFallbackLocale('en_US');
        $product->addTranslation($translation);

        return $product;
    }

    private function createChannel(string $code): Channel
    {
        $channel = new Channel();
        $channel->setCode($code);
        $channel->setName('Test Store');
        $channel->setHostname('https://example.com');
        $channel->setContactEmail('test@example.com');

        return $channel;
    }
}
