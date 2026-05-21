<?php

declare(strict_types=1);

namespace Tests\Webgriffe\SyliusMailchimpPlugin\Unit\EventSubscriber;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Sylius\Component\Core\Model\OrderInterface;
use Symfony\Component\EventDispatcher\GenericEvent;
use Tests\Webgriffe\SyliusMailchimpPlugin\Entity\Order\Order;
use Webgriffe\SyliusMailchimpPlugin\Enqueuer\CartEnqueuerInterface;
use Webgriffe\SyliusMailchimpPlugin\Enqueuer\OrderEnqueuerInterface;
use Webgriffe\SyliusMailchimpPlugin\EventSubscriber\OrderSubscriber;

final class OrderSubscriberTest extends TestCase
{
    private MockObject&CartEnqueuerInterface $cartEnqueuer;

    private MockObject&OrderEnqueuerInterface $orderEnqueuer;

    private OrderSubscriber $subscriberNoCarts;

    private OrderSubscriber $subscriberWithUnpaidAsCarts;

    protected function setUp(): void
    {
        $this->cartEnqueuer = $this->createMock(CartEnqueuerInterface::class);
        $this->orderEnqueuer = $this->createMock(OrderEnqueuerInterface::class);
        $this->subscriberNoCarts = new OrderSubscriber($this->cartEnqueuer, $this->orderEnqueuer, false, new NullLogger());
        $this->subscriberWithUnpaidAsCarts = new OrderSubscriber($this->cartEnqueuer, $this->orderEnqueuer, true, new NullLogger());
    }

    public function testGetSubscribedEvents(): void
    {
        $events = OrderSubscriber::getSubscribedEvents();

        self::assertArrayHasKey('sylius.order.post_update', $events);
        self::assertArrayHasKey('sylius.order.post_complete', $events);
    }

    public function testOnOrderPostUpdateDoesNotEnqueueCartForCartState(): void
    {
        $order = new Order();

        $this->cartEnqueuer->expects(self::never())->method('enqueue');
        $this->orderEnqueuer->expects(self::never())->method('enqueue');

        $this->subscriberNoCarts->onOrderPostUpdate(new GenericEvent($order));
    }

    public function testOnOrderPostUpdateIgnoresNewStateWhenSendUnpaidOrdersAsCartsDisabled(): void
    {
        $order = new Order();
        $order->setState(OrderInterface::STATE_NEW);

        $this->cartEnqueuer->expects(self::never())->method('enqueue');

        $this->subscriberNoCarts->onOrderPostUpdate(new GenericEvent($order));
    }

    public function testOnOrderPostUpdateEnqueuesCartForNewStateWhenSendUnpaidOrdersAsCartsEnabled(): void
    {
        $order = new Order();
        $order->setState(OrderInterface::STATE_NEW);

        $this->cartEnqueuer->expects(self::once())->method('enqueue')->with($order);

        $this->subscriberWithUnpaidAsCarts->onOrderPostUpdate(new GenericEvent($order));
    }

    public function testOnOrderPostUpdateIgnoresFulfilledState(): void
    {
        $order = new Order();
        $order->setState(OrderInterface::STATE_FULFILLED);

        $this->cartEnqueuer->expects(self::never())->method('enqueue');

        $this->subscriberWithUnpaidAsCarts->onOrderPostUpdate(new GenericEvent($order));
    }

    public function testOnOrderPostUpdateIgnoresNonOrderSubject(): void
    {
        $this->cartEnqueuer->expects(self::never())->method('enqueue');
        $this->orderEnqueuer->expects(self::never())->method('enqueue');

        $this->subscriberNoCarts->onOrderPostUpdate(new GenericEvent(new \stdClass()));
    }

    public function testOnOrderPostCompleteEnqueuesOrderOnly(): void
    {
        $order = new Order();

        $this->cartEnqueuer->expects(self::never())->method('enqueueRemoval');
        $this->orderEnqueuer->expects(self::once())->method('enqueue')->with($order, isInRealTime: true);

        $this->subscriberNoCarts->onOrderPostComplete(new GenericEvent($order));
    }

    public function testOnOrderPostCompleteIgnoresNonOrderSubject(): void
    {
        $this->cartEnqueuer->expects(self::never())->method('enqueueRemoval');
        $this->orderEnqueuer->expects(self::never())->method('enqueue');

        $this->subscriberNoCarts->onOrderPostComplete(new GenericEvent(new \stdClass()));
    }
}
