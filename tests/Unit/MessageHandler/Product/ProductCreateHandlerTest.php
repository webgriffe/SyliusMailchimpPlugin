<?php

declare(strict_types=1);

namespace Tests\Webgriffe\SyliusMailchimpPlugin\Unit\MessageHandler\Product;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Sylius\Component\Channel\Repository\ChannelRepositoryInterface;
use Sylius\Component\Core\Model\Channel;
use Sylius\Component\Core\Model\Product;
use Sylius\Component\Core\Repository\ProductRepositoryInterface;
use Tests\Webgriffe\SyliusMailchimpPlugin\Entity\Channel\Channel as MailchimpChannel;
use Webgriffe\SyliusMailchimpPlugin\Client\MailchimpClientInterface;
use Webgriffe\SyliusMailchimpPlugin\Message\Product\ProductCreate;
use Webgriffe\SyliusMailchimpPlugin\MessageHandler\Product\ProductCreateHandler;
use Webgriffe\SyliusMailchimpPlugin\Provider\AudienceProviderInterface;
use Webgriffe\SyliusMailchimpPlugin\Resolver\StoreIdentifierResolverInterface;
use Webgriffe\SyliusMailchimpPlugin\ValueObject\Audience;

final class ProductCreateHandlerTest extends TestCase
{
    private MockObject&ProductRepositoryInterface $productRepository;

    private MockObject&ChannelRepositoryInterface $channelRepository;

    private MockObject&AudienceProviderInterface $audienceProvider;

    private MockObject&StoreIdentifierResolverInterface $storeIdentifierResolver;

    private MockObject&MailchimpClientInterface $mailchimpClient;

    protected function setUp(): void
    {
        $this->productRepository = $this->createMock(ProductRepositoryInterface::class);
        $this->channelRepository = $this->createMock(ChannelRepositoryInterface::class);
        $this->audienceProvider = $this->createMock(AudienceProviderInterface::class);
        $this->storeIdentifierResolver = $this->createMock(StoreIdentifierResolverInterface::class);
        $this->mailchimpClient = $this->createMock(MailchimpClientInterface::class);
    }

    public function test_skips_when_product_not_found(): void
    {
        $this->productRepository->method('find')->with(1)->willReturn(null);
        $this->mailchimpClient->expects(self::never())->method('upsertProduct');

        $handler = $this->buildHandler();
        $handler(new ProductCreate(1, 10, 'en_US'));
    }

    public function test_skips_when_channel_not_found(): void
    {
        $this->productRepository->method('find')->with(1)->willReturn(new Product());
        $this->channelRepository->method('find')->with(10)->willReturn(null);
        $this->mailchimpClient->expects(self::never())->method('upsertProduct');

        $handler = $this->buildHandler();
        $handler(new ProductCreate(1, 10, 'en_US'));
    }

    public function test_skips_when_channel_is_not_mailchimp_aware(): void
    {
        $this->productRepository->method('find')->with(1)->willReturn(new Product());
        $this->channelRepository->method('find')->with(10)->willReturn(new Channel());
        $this->mailchimpClient->expects(self::never())->method('upsertProduct');

        $handler = $this->buildHandler();
        $handler(new ProductCreate(1, 10, 'en_US'));
    }

    public function test_upserts_product_in_resolved_store(): void
    {
        $product = new Product();
        $channel = new MailchimpChannel();
        $audience = new Audience('list123', $channel);

        $this->productRepository->method('find')->with(1)->willReturn($product);
        $this->channelRepository->method('find')->with(10)->willReturn($channel);
        $this->audienceProvider->method('getAudience')->with($channel, 'en_US')->willReturn($audience);
        $this->storeIdentifierResolver->method('resolve')->with($audience)->willReturn('web-store');
        $this->mailchimpClient->expects(self::once())->method('upsertProduct')->with('web-store', $product, $channel, 'en_US');

        $handler = $this->buildHandler();
        $handler(new ProductCreate(1, 10, 'en_US'));
    }

    private function buildHandler(): ProductCreateHandler
    {
        return new ProductCreateHandler(
            $this->productRepository,
            $this->channelRepository,
            $this->audienceProvider,
            $this->storeIdentifierResolver,
            $this->mailchimpClient,
            new NullLogger(),
        );
    }
}
