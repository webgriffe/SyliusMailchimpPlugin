<?php

declare(strict_types=1);

namespace Tests\Webgriffe\SyliusMailchimpPlugin\Unit\MessageHandler\Store;

use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Sylius\Component\Channel\Repository\ChannelRepositoryInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Webgriffe\SyliusMailchimpPlugin\Client\MailchimpClientInterface;
use Webgriffe\SyliusMailchimpPlugin\Mapper\StoreMapper;
use Webgriffe\SyliusMailchimpPlugin\Message\Store\StoreCreate;
use Webgriffe\SyliusMailchimpPlugin\Message\Store\StoreRemove;
use Webgriffe\SyliusMailchimpPlugin\Message\Store\StoreUpdate;
use Webgriffe\SyliusMailchimpPlugin\MessageHandler\Store\StoreCreateHandler;
use Webgriffe\SyliusMailchimpPlugin\MessageHandler\Store\StoreRemoveHandler;
use Webgriffe\SyliusMailchimpPlugin\MessageHandler\Store\StoreUpdateHandler;
use Webgriffe\SyliusMailchimpPlugin\Model\ChannelMailchimpAwareInterface;

final class StoreHandlersTest extends TestCase
{
    private ChannelRepositoryInterface $channelRepository;

    private MailchimpClientInterface $mailchimpClient;

    protected function setUp(): void
    {
        $this->channelRepository = $this->createMock(ChannelRepositoryInterface::class);
        $this->mailchimpClient = $this->createMock(MailchimpClientInterface::class);
    }

    public function testStoreCreateCallsUpsertStore(): void
    {
        $channel = $this->createChannelMock(id: 1, code: 'WEB');
        $this->channelRepository->method('find')->with(1)->willReturn($channel);
        $this->mailchimpClient->expects($this->once())->method('upsertStore');

        $handler = new StoreCreateHandler($this->channelRepository, new StoreMapper(), $this->mailchimpClient, new NullLogger());
        $handler(new StoreCreate(1));
    }

    public function testStoreCreateSkipsWhenChannelNotFound(): void
    {
        $this->channelRepository->method('find')->with(99)->willReturn(null);
        $this->mailchimpClient->expects($this->never())->method('upsertStore');

        $handler = new StoreCreateHandler($this->channelRepository, new StoreMapper(), $this->mailchimpClient, new NullLogger());
        $handler(new StoreCreate(99));
    }

    public function testStoreUpdateCallsUpsertStore(): void
    {
        $channel = $this->createChannelMock(id: 2, code: 'STORE2');
        $this->channelRepository->method('find')->with(2)->willReturn($channel);
        $this->mailchimpClient->expects($this->once())->method('upsertStore');

        $handler = new StoreUpdateHandler($this->channelRepository, new StoreMapper(), $this->mailchimpClient, new NullLogger());
        $handler(new StoreUpdate(2));
    }

    public function testStoreRemoveCallsRemoveStore(): void
    {
        $this->mailchimpClient->expects($this->once())->method('removeStore')->with('web-store');

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

    private function createChannelMock(int $id, string $code): ChannelInterface&ChannelMailchimpAwareInterface
    {
        /** @var ChannelInterface&ChannelMailchimpAwareInterface $channel */
        $channel = $this->createMockForIntersectionOfInterfaces([ChannelInterface::class, ChannelMailchimpAwareInterface::class]);
        $channel->method('getId')->willReturn($id);
        $channel->method('getCode')->willReturn($code);
        $channel->method('getName')->willReturn('Test Store');
        $channel->method('getHostname')->willReturn('https://example.com');
        $channel->method('getContactEmail')->willReturn('test@example.com');
        $channel->method('getLocales')->willReturn(new ArrayCollection([]));
        $channel->method('getCurrencies')->willReturn(new ArrayCollection([]));

        return $channel;
    }
}
