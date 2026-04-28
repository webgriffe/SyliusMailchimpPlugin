<?php

declare(strict_types=1);

namespace Tests\Webgriffe\SyliusMailchimpPlugin\Unit\Enqueuer;

use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Sylius\Component\Core\Model\ChannelInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Webgriffe\SyliusMailchimpPlugin\Enqueuer\StoreEnqueuer;
use Webgriffe\SyliusMailchimpPlugin\Mapper\StoreMapper;
use Webgriffe\SyliusMailchimpPlugin\Message\Store\StoreCreate;
use Webgriffe\SyliusMailchimpPlugin\Model\ChannelMailchimpAwareInterface;

final class StoreEnqueuerTest extends TestCase
{
    private MessageBusInterface $messageBus;

    private StoreEnqueuer $enqueuer;

    protected function setUp(): void
    {
        $this->messageBus = $this->createMock(MessageBusInterface::class);
        $this->enqueuer = new StoreEnqueuer($this->messageBus, new StoreMapper(), new NullLogger());
    }

    public function testEnqueueDispatchesStoreCreate(): void
    {
        $channel = $this->createChannelMock(id: 1, code: 'WEB');

        $this->messageBus->expects($this->once())
            ->method('dispatch')
            ->with($this->isInstanceOf(StoreCreate::class))
            ->willReturnCallback(fn (object $msg) => new Envelope($msg));

        $this->enqueuer->enqueue($channel);
    }

    public function testEnqueueSkipsWhenChannelHasNoIntId(): void
    {
        $channel = $this->createChannelMock(id: null, code: 'WEB');

        $this->messageBus->expects($this->never())->method('dispatch');

        $this->enqueuer->enqueue($channel);
    }

    /** @return ChannelInterface&ChannelMailchimpAwareInterface */
    private function createChannelMock(int|null $id, string $code): ChannelInterface&ChannelMailchimpAwareInterface
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
