<?php

declare(strict_types=1);

namespace Tests\Webgriffe\SyliusMailchimpPlugin\Unit\MessageHandler\Store;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Sylius\Component\Channel\Repository\ChannelRepositoryInterface;
use Tests\Webgriffe\SyliusMailchimpPlugin\Entity\Channel\Channel;
use Webgriffe\SyliusMailchimpPlugin\Client\MailchimpClientInterface;
use Webgriffe\SyliusMailchimpPlugin\Mapper\StoreMapperInterface;
use Webgriffe\SyliusMailchimpPlugin\Message\Store\StoreCreate;
use Webgriffe\SyliusMailchimpPlugin\Message\Store\StoreRemove;
use Webgriffe\SyliusMailchimpPlugin\Message\Store\StoreUpdate;
use Webgriffe\SyliusMailchimpPlugin\MessageHandler\Store\StoreCreateHandler;
use Webgriffe\SyliusMailchimpPlugin\MessageHandler\Store\StoreRemoveHandler;
use Webgriffe\SyliusMailchimpPlugin\MessageHandler\Store\StoreUpdateHandler;
use Webgriffe\SyliusMailchimpPlugin\Provider\AudienceProviderInterface;
use Webgriffe\SyliusMailchimpPlugin\ValueObject\Audience;
use Webgriffe\SyliusMailchimpPlugin\ValueObject\Store;

final class StoreHandlersTest extends TestCase
{
    private MockObject&ChannelRepositoryInterface $channelRepository;

    private MockObject&AudienceProviderInterface $audienceProvider;

    private MockObject&StoreMapperInterface $storeMapper;

    private MockObject&MailchimpClientInterface $mailchimpClient;

    protected function setUp(): void
    {
        $this->channelRepository = $this->createMock(ChannelRepositoryInterface::class);
        $this->audienceProvider = $this->createMock(AudienceProviderInterface::class);
        $this->storeMapper = $this->createMock(StoreMapperInterface::class);
        $this->mailchimpClient = $this->createMock(MailchimpClientInterface::class);
    }

    public function testStoreCreateCallsUpsertStore(): void
    {
        $channel = $this->buildChannel(id: 1, code: 'WEB');
        $audience = new Audience('aud1', $channel);
        $store = new Store(id: 'WEB-aud1', name: 'Web Store', domain: 'example.com', emailAddress: 'shop@example.com', currencyCode: 'EUR', primaryLocale: 'en', listId: 'aud1');

        $this->channelRepository->method('find')->with(1)->willReturn($channel);
        $this->audienceProvider->expects(self::once())->method('getAudience')->with($channel, null)->willReturn($audience);
        $this->storeMapper->expects(self::once())->method('map')->with($audience)->willReturn($store);
        $this->mailchimpClient->expects(self::once())->method('upsertStore')->with($store);

        $handler = new StoreCreateHandler($this->channelRepository, $this->audienceProvider, $this->storeMapper, $this->mailchimpClient, new NullLogger());
        $handler(new StoreCreate(1));
    }

    public function testStoreCreateSkipsWhenChannelNotFound(): void
    {
        $this->channelRepository->method('find')->with(99)->willReturn(null);
        $this->audienceProvider->expects(self::never())->method('getAudience');
        $this->mailchimpClient->expects(self::never())->method('upsertStore');

        $handler = new StoreCreateHandler($this->channelRepository, $this->audienceProvider, $this->storeMapper, $this->mailchimpClient, new NullLogger());
        $handler(new StoreCreate(99));
    }

    public function testStoreUpdateCallsUpsertStore(): void
    {
        $channel = $this->buildChannel(id: 2, code: 'STORE2');
        $audience = new Audience('aud2', $channel);
        $store = new Store(id: 'STORE2-aud2', name: 'Store 2', domain: 'example.com', emailAddress: 'shop@example.com', currencyCode: 'EUR', primaryLocale: 'en', listId: 'aud2');

        $this->channelRepository->method('find')->with(2)->willReturn($channel);
        $this->audienceProvider->expects(self::once())->method('getAudience')->with($channel, null)->willReturn($audience);
        $this->storeMapper->expects(self::once())->method('map')->with($audience)->willReturn($store);
        $this->mailchimpClient->expects(self::once())->method('upsertStore')->with($store);

        $handler = new StoreUpdateHandler($this->channelRepository, $this->audienceProvider, $this->storeMapper, $this->mailchimpClient, new NullLogger());
        $handler(new StoreUpdate(2));
    }

    public function testStoreRemoveCallsRemoveStore(): void
    {
        $this->mailchimpClient->expects(self::once())->method('removeStore')->with('web-store');

        $handler = new StoreRemoveHandler($this->mailchimpClient, new NullLogger());
        $handler(new StoreRemove('web-store'));
    }

    public function testStoreRemoveRethrowsException(): void
    {
        $this->mailchimpClient->method('removeStore')->willThrowException(new \RuntimeException('API error'));

        $handler = new StoreRemoveHandler($this->mailchimpClient, new NullLogger());

        $this->expectException(\RuntimeException::class);
        $handler(new StoreRemove('web-store'));
    }

    private function buildChannel(int $id, string $code): Channel
    {
        $channel = new Channel();
        $channel->setCode($code);
        $channel->setMailchimpAudienceId('aud' . $id);

        return $channel;
    }
}
