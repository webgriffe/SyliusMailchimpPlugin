<?php

declare(strict_types=1);

namespace Tests\Webgriffe\SyliusMailchimpPlugin\Unit\Enqueuer;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Tests\Webgriffe\SyliusMailchimpPlugin\Entity\Channel\Channel;
use Tests\Webgriffe\SyliusMailchimpPlugin\Entity\Order\Order;
use Webgriffe\SyliusMailchimpPlugin\Enqueuer\OrderEnqueuer;
use Webgriffe\SyliusMailchimpPlugin\Message\Order\OrderCreate;
use Webgriffe\SyliusMailchimpPlugin\Message\Order\OrderRemove;
use Webgriffe\SyliusMailchimpPlugin\Message\Order\OrderUpdate;
use Webgriffe\SyliusMailchimpPlugin\Provider\AudienceProviderInterface;
use Webgriffe\SyliusMailchimpPlugin\Resolver\StoreIdentifierResolverInterface;
use Webgriffe\SyliusMailchimpPlugin\ValueObject\Audience;

final class OrderEnqueuerTest extends TestCase
{
    private MessageBusInterface $messageBus;

    private MockObject&AudienceProviderInterface $audienceProvider;

    private MockObject&StoreIdentifierResolverInterface $storeIdentifierResolver;

    private OrderEnqueuer $enqueuer;

    protected function setUp(): void
    {
        $this->messageBus = $this->createMock(MessageBusInterface::class);
        $this->audienceProvider = $this->createMock(AudienceProviderInterface::class);
        $this->storeIdentifierResolver = $this->createMock(StoreIdentifierResolverInterface::class);
        $this->enqueuer = new OrderEnqueuer(
            $this->messageBus,
            new NullLogger(),
            $this->audienceProvider,
            $this->storeIdentifierResolver,
        );
    }

    public function testEnqueueDispatchesOrderCreateWhenNoMailchimpOrderId(): void
    {
        $channel = $this->createChannel(id: 1, code: 'WEB');
        $order = $this->createOrder(id: 10, channel: $channel, mailchimpOrderId: null);

        $this->messageBus->expects($this->once())
            ->method('dispatch')
            ->with($this->isInstanceOf(OrderCreate::class))
            ->willReturnCallback(fn (object $msg) => new Envelope($msg));

        $this->enqueuer->enqueue($order);
    }

    public function testEnqueueDispatchesOrderUpdateWhenMailchimpOrderIdExists(): void
    {
        $channel = $this->createChannel(id: 1, code: 'WEB');
        $order = $this->createOrder(id: 10, channel: $channel, mailchimpOrderId: 'order-10');

        $this->messageBus->expects($this->once())
            ->method('dispatch')
            ->with($this->isInstanceOf(OrderUpdate::class))
            ->willReturnCallback(fn (object $msg) => new Envelope($msg));

        $this->enqueuer->enqueue($order);
    }

    public function testEnqueuePassesIsInRealTimeFlag(): void
    {
        $channel = $this->createChannel(id: 1, code: 'WEB');
        $order = $this->createOrder(id: 10, channel: $channel, mailchimpOrderId: null);

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

    public function testEnqueueRemovalDispatchesOrderRemoveWithCorrectStoreId(): void
    {
        $channel = $this->createChannel(id: 1, code: 'WEB', audienceId: 'abc123');
        $order = $this->createOrder(id: 10, channel: $channel, mailchimpOrderId: 'order-10');

        $audience = new Audience('abc123', $channel);
        $this->audienceProvider->method('getAudience')->willReturn($audience);
        $this->storeIdentifierResolver->method('resolve')->willReturn('WEB-abc123');

        $this->messageBus->expects($this->once())
            ->method('dispatch')
            ->with($this->equalTo(new OrderRemove('WEB-abc123', 'order-10')))
            ->willReturnCallback(fn (object $msg) => new Envelope($msg));

        $this->enqueuer->enqueueRemoval($order);
    }

    public function testEnqueueRemovalSkipsWhenNoOrderId(): void
    {
        $channel = $this->createChannel(id: 1, code: 'WEB');
        $order = $this->createOrder(id: 10, channel: $channel, mailchimpOrderId: null);

        $this->messageBus->expects($this->never())->method('dispatch');

        $this->enqueuer->enqueueRemoval($order);
    }

    private function createOrder(int $id, Channel $channel, ?string $mailchimpOrderId): Order
    {
        $order = new Order();
        self::setId($order, $id);
        $order->setChannel($channel);
        if ($mailchimpOrderId !== null) {
            $order->setMailchimpOrderId($mailchimpOrderId);
        }

        return $order;
    }

    private function createChannel(int $id, string $code, string $audienceId = 'audience123'): Channel
    {
        $channel = new Channel();
        self::setId($channel, $id);
        $channel->setCode($code);
        $channel->setMailchimpAudienceId($audienceId);

        return $channel;
    }

    private static function setId(object $entity, int $id): void
    {
        $ref = new \ReflectionProperty($entity, 'id');
        $ref->setValue($entity, $id);
    }
}
