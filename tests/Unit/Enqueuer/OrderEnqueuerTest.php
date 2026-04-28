<?php

declare(strict_types=1);

namespace Tests\Webgriffe\SyliusMailchimpPlugin\Unit\Enqueuer;

use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Webgriffe\SyliusMailchimpPlugin\Enqueuer\OrderEnqueuer;
use Webgriffe\SyliusMailchimpPlugin\Message\Order\OrderCreate;
use Webgriffe\SyliusMailchimpPlugin\Message\Order\OrderRemove;
use Webgriffe\SyliusMailchimpPlugin\Message\Order\OrderUpdate;
use Webgriffe\SyliusMailchimpPlugin\Model\ChannelMailchimpAwareInterface;
use Webgriffe\SyliusMailchimpPlugin\Model\MailchimpOrderAwareInterface;

final class OrderEnqueuerTest extends TestCase
{
    private MessageBusInterface $messageBus;

    private OrderEnqueuer $enqueuer;

    protected function setUp(): void
    {
        $this->messageBus = $this->createMock(MessageBusInterface::class);
        $this->enqueuer = new OrderEnqueuer($this->messageBus, new NullLogger());
    }

    public function testEnqueueDispatchesOrderCreateWhenNoMailchimpOrderId(): void
    {
        $channel = $this->createChannelMock(id: 1, code: 'WEB');
        $order = $this->createOrderMock(id: 10, channel: $channel, mailchimpOrderId: null);

        $this->messageBus->expects($this->once())
            ->method('dispatch')
            ->with($this->isInstanceOf(OrderCreate::class))
            ->willReturnCallback(fn (object $msg) => new Envelope($msg));

        $this->enqueuer->enqueue($order);
    }

    public function testEnqueueDispatchesOrderUpdateWhenMailchimpOrderIdExists(): void
    {
        $channel = $this->createChannelMock(id: 1, code: 'WEB');
        $order = $this->createOrderMock(id: 10, channel: $channel, mailchimpOrderId: 'order-10');

        $this->messageBus->expects($this->once())
            ->method('dispatch')
            ->with($this->isInstanceOf(OrderUpdate::class))
            ->willReturnCallback(fn (object $msg) => new Envelope($msg));

        $this->enqueuer->enqueue($order);
    }

    public function testEnqueuePassesIsInRealTimeFlag(): void
    {
        $channel = $this->createChannelMock(id: 1, code: 'WEB');
        $order = $this->createOrderMock(id: 10, channel: $channel, mailchimpOrderId: null);

        $dispatched = null;
        $this->messageBus->method('dispatch')
            ->willReturnCallback(function (object $msg) use (&$dispatched) {
                $dispatched = $msg;

                return new Envelope($msg);
            });

        $this->enqueuer->enqueue($order, isInRealTime: true);

        $this->assertInstanceOf(OrderCreate::class, $dispatched);
        $this->assertTrue($dispatched->isInRealTime);
    }

    public function testEnqueueRemovalDispatchesOrderRemove(): void
    {
        $channel = $this->createChannelMock(id: 1, code: 'WEB');
        $order = $this->createOrderMock(id: 10, channel: $channel, mailchimpOrderId: 'order-10');

        $this->messageBus->expects($this->once())
            ->method('dispatch')
            ->with($this->equalTo(new OrderRemove('WEB', 'order-10')))
            ->willReturnCallback(fn (object $msg) => new Envelope($msg));

        $this->enqueuer->enqueueRemoval($order);
    }

    public function testEnqueueRemovalSkipsWhenNoOrderId(): void
    {
        $channel = $this->createChannelMock(id: 1, code: 'WEB');
        $order = $this->createOrderMock(id: 10, channel: $channel, mailchimpOrderId: null);

        $this->messageBus->expects($this->never())->method('dispatch');

        $this->enqueuer->enqueueRemoval($order);
    }

    /** @return OrderInterface&MailchimpOrderAwareInterface */
    private function createOrderMock(int $id, ChannelInterface $channel, ?string $mailchimpOrderId): OrderInterface&MailchimpOrderAwareInterface
    {
        /** @var OrderInterface&MailchimpOrderAwareInterface $order */
        $order = $this->createMockForIntersectionOfInterfaces([OrderInterface::class, MailchimpOrderAwareInterface::class]);
        $order->method('getId')->willReturn($id);
        $order->method('getChannel')->willReturn($channel);
        $order->method('getMailchimpOrderId')->willReturn($mailchimpOrderId);

        return $order;
    }

    /** @return ChannelInterface&ChannelMailchimpAwareInterface */
    private function createChannelMock(int $id, string $code): ChannelInterface&ChannelMailchimpAwareInterface
    {
        /** @var ChannelInterface&ChannelMailchimpAwareInterface $channel */
        $channel = $this->createMockForIntersectionOfInterfaces([ChannelInterface::class, ChannelMailchimpAwareInterface::class]);
        $channel->method('getId')->willReturn($id);
        $channel->method('getCode')->willReturn($code);

        return $channel;
    }
}
