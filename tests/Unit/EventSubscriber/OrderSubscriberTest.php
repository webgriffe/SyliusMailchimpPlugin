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

    public function test_get_subscribed_events(): void
    {
        $events = OrderSubscriber::getSubscribedEvents();

        self::assertArrayHasKey('sylius.order.post_update', $events);
        self::assertArrayHasKey('sylius.order.post_complete', $events);
    }

    public function test_on_order_post_update_does_not_enqueue_cart_for_cart_state(): void
    {
        $order = new Order();

        $this->cartEnqueuer->expects(self::never())->method('enqueue');
        $this->orderEnqueuer->expects(self::never())->method('enqueue');

        $this->subscriberNoCarts->onOrderPostUpdate(new GenericEvent($order));
    }

    public function test_on_order_post_update_ignores_new_state_when_send_unpaid_orders_as_carts_disabled(): void
    {
        $order = new Order();
        $order->setState(OrderInterface::STATE_NEW);

        $this->cartEnqueuer->expects(self::never())->method('enqueue');

        $this->subscriberNoCarts->onOrderPostUpdate(new GenericEvent($order));
    }

    public function test_on_order_post_update_enqueues_cart_for_new_state_when_send_unpaid_orders_as_carts_enabled(): void
    {
        $order = new Order();
        $order->setState(OrderInterface::STATE_NEW);

        $this->cartEnqueuer->expects(self::once())->method('enqueue')->with($order);

        $this->subscriberWithUnpaidAsCarts->onOrderPostUpdate(new GenericEvent($order));
    }

    public function test_on_order_post_update_ignores_fulfilled_state(): void
    {
        $order = new Order();
        $order->setState(OrderInterface::STATE_FULFILLED);

        $this->cartEnqueuer->expects(self::never())->method('enqueue');

        $this->subscriberWithUnpaidAsCarts->onOrderPostUpdate(new GenericEvent($order));
    }

    public function test_on_order_post_update_ignores_non_order_subject(): void
    {
        $this->cartEnqueuer->expects(self::never())->method('enqueue');
        $this->orderEnqueuer->expects(self::never())->method('enqueue');

        $this->subscriberNoCarts->onOrderPostUpdate(new GenericEvent(new \stdClass()));
    }

    public function test_on_order_post_complete_enqueues_order_only(): void
    {
        $order = new Order();

        $this->cartEnqueuer->expects(self::never())->method('enqueueRemoval');
        $this->orderEnqueuer->expects(self::once())->method('enqueue')->with($order, isInRealTime: true);

        $this->subscriberNoCarts->onOrderPostComplete(new GenericEvent($order));
    }

    public function test_on_order_post_complete_ignores_non_order_subject(): void
    {
        $this->cartEnqueuer->expects(self::never())->method('enqueueRemoval');
        $this->orderEnqueuer->expects(self::never())->method('enqueue');

        $this->subscriberNoCarts->onOrderPostComplete(new GenericEvent(new \stdClass()));
    }
}
