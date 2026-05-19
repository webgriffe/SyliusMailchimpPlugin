<?php

declare(strict_types=1);

namespace Tests\Webgriffe\SyliusMailchimpPlugin\Unit\Enqueuer;

use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Tests\Webgriffe\SyliusMailchimpPlugin\Entity\Channel\Channel;
use Tests\Webgriffe\SyliusMailchimpPlugin\Entity\Order\Order;
use Webgriffe\SyliusMailchimpPlugin\Enqueuer\CartEnqueuer;
use Webgriffe\SyliusMailchimpPlugin\Message\Cart\CartCreate;
use Webgriffe\SyliusMailchimpPlugin\Message\Cart\CartRemove;
use Webgriffe\SyliusMailchimpPlugin\Message\Cart\CartUpdate;

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
        $channel = $this->createChannel(id: 1, code: 'WEB');
        $order = $this->createOrder(id: 5, channel: $channel, mailchimpCartId: null);

        $this->messageBus->expects($this->once())
            ->method('dispatch')
            ->with($this->isInstanceOf(CartCreate::class))
            ->willReturnCallback(fn (object $msg) => new Envelope($msg));

        $this->enqueuer->enqueue($order);
    }

    public function testEnqueueDispatchesCartUpdateWhenMailchimpCartIdExists(): void
    {
        $channel = $this->createChannel(id: 1, code: 'WEB');
        $order = $this->createOrder(id: 5, channel: $channel, mailchimpCartId: 'existing-cart-id');

        $this->messageBus->expects($this->once())
            ->method('dispatch')
            ->with($this->isInstanceOf(CartUpdate::class))
            ->willReturnCallback(fn (object $msg) => new Envelope($msg));

        $this->enqueuer->enqueue($order);
    }

    public function testEnqueueRemovalDispatchesCartRemove(): void
    {
        $channel = $this->createChannel(id: 1, code: 'WEB');
        $order = $this->createOrder(id: 5, channel: $channel, mailchimpCartId: 'cart-token-abc');

        $this->messageBus->expects($this->once())
            ->method('dispatch')
            ->with($this->equalTo(new CartRemove('WEB', 'cart-token-abc')))
            ->willReturnCallback(fn (object $msg) => new Envelope($msg));

        $this->enqueuer->enqueueRemoval($order);
    }

    public function testEnqueueRemovalSkipsWhenNoCartId(): void
    {
        $channel = $this->createChannel(id: 1, code: 'WEB');
        $order = $this->createOrder(id: 5, channel: $channel, mailchimpCartId: null);

        $this->messageBus->expects($this->never())->method('dispatch');

        $this->enqueuer->enqueueRemoval($order);
    }

    private function createOrder(int $id, Channel $channel, ?string $mailchimpCartId): Order
    {
        $order = new Order();
        self::setId($order, $id);
        $order->setChannel($channel);
        if ($mailchimpCartId !== null) {
            $order->setMailchimpCartId($mailchimpCartId);
        }

        return $order;
    }

    private function createChannel(int $id, string $code): Channel
    {
        $channel = new Channel();
        self::setId($channel, $id);
        $channel->setCode($code);

        return $channel;
    }

    private static function setId(object $entity, int $id): void
    {
        $ref = new \ReflectionProperty($entity, 'id');
        $ref->setValue($entity, $id);
    }
}
