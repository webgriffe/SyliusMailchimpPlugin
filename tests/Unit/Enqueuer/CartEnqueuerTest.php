<?php

declare(strict_types=1);

namespace Tests\Webgriffe\SyliusMailchimpPlugin\Unit\Enqueuer;

use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Webgriffe\SyliusMailchimpPlugin\Enqueuer\CartEnqueuer;
use Webgriffe\SyliusMailchimpPlugin\Message\Cart\CartCreate;
use Webgriffe\SyliusMailchimpPlugin\Message\Cart\CartRemove;
use Webgriffe\SyliusMailchimpPlugin\Message\Cart\CartUpdate;
use Webgriffe\SyliusMailchimpPlugin\Model\ChannelMailchimpAwareInterface;
use Webgriffe\SyliusMailchimpPlugin\Model\MailchimpOrderAwareInterface;

final class CartEnqueuerTest extends TestCase
{
    private MessageBusInterface $messageBus;

    private CartEnqueuer $enqueuer;

    protected function setUp(): void
    {
        $this->messageBus = $this->createMock(MessageBusInterface::class);
        $this->enqueuer = new CartEnqueuer($this->messageBus, new NullLogger());
    }

    public function testEnqueueDispatchesCartCreateWhenNoMailchimpCartId(): void
    {
        $channel = $this->createChannelMock(id: 1, code: 'WEB');
        $order = $this->createOrderMock(id: 5, channel: $channel, mailchimpCartId: null);

        $this->messageBus->expects($this->once())
            ->method('dispatch')
            ->with($this->isInstanceOf(CartCreate::class))
            ->willReturnCallback(fn (object $msg) => new Envelope($msg));

        $this->enqueuer->enqueue($order);
    }

    public function testEnqueueDispatchesCartUpdateWhenMailchimpCartIdExists(): void
    {
        $channel = $this->createChannelMock(id: 1, code: 'WEB');
        $order = $this->createOrderMock(id: 5, channel: $channel, mailchimpCartId: 'existing-cart-id');

        $this->messageBus->expects($this->once())
            ->method('dispatch')
            ->with($this->isInstanceOf(CartUpdate::class))
            ->willReturnCallback(fn (object $msg) => new Envelope($msg));

        $this->enqueuer->enqueue($order);
    }

    public function testEnqueueRemovalDispatchesCartRemove(): void
    {
        $channel = $this->createChannelMock(id: 1, code: 'WEB');
        $order = $this->createOrderMock(id: 5, channel: $channel, mailchimpCartId: 'cart-token-abc');

        $this->messageBus->expects($this->once())
            ->method('dispatch')
            ->with($this->equalTo(new CartRemove('WEB', 'cart-token-abc')))
            ->willReturnCallback(fn (object $msg) => new Envelope($msg));

        $this->enqueuer->enqueueRemoval($order);
    }

    public function testEnqueueRemovalSkipsWhenNoCartId(): void
    {
        $channel = $this->createChannelMock(id: 1, code: 'WEB');
        $order = $this->createOrderMock(id: 5, channel: $channel, mailchimpCartId: null);

        $this->messageBus->expects($this->never())->method('dispatch');

        $this->enqueuer->enqueueRemoval($order);
    }

    /** @return OrderInterface&MailchimpOrderAwareInterface */
    private function createOrderMock(int $id, ChannelInterface $channel, ?string $mailchimpCartId): OrderInterface&MailchimpOrderAwareInterface
    {
        /** @var OrderInterface&MailchimpOrderAwareInterface $order */
        $order = $this->createMockForIntersectionOfInterfaces([OrderInterface::class, MailchimpOrderAwareInterface::class]);
        $order->method('getId')->willReturn($id);
        $order->method('getChannel')->willReturn($channel);
        $order->method('getMailchimpCartId')->willReturn($mailchimpCartId);

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
