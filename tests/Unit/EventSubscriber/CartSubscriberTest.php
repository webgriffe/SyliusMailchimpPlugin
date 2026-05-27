<?php

declare(strict_types=1);

namespace Tests\Webgriffe\SyliusMailchimpPlugin\Unit\EventSubscriber;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Sylius\Bundle\OrderBundle\Controller\AddToCartCommandInterface;
use Sylius\Component\Core\Model\OrderItemInterface;
use Sylius\Component\Order\SyliusCartEvents;
use Symfony\Component\EventDispatcher\GenericEvent;
use Tests\Webgriffe\SyliusMailchimpPlugin\Entity\Order\Order;
use Tests\Webgriffe\SyliusMailchimpPlugin\Unit\ReflectionIdTrait;
use Webgriffe\SyliusMailchimpPlugin\Enqueuer\CartEnqueuerInterface;
use Webgriffe\SyliusMailchimpPlugin\EventSubscriber\CartSubscriber;

final class CartSubscriberTest extends TestCase
{
    use ReflectionIdTrait;

    private MockObject&CartEnqueuerInterface $cartEnqueuer;

    private CartSubscriber $subscriber;

    protected function setUp(): void
    {
        $this->cartEnqueuer = $this->createMock(CartEnqueuerInterface::class);
        $this->subscriber = new CartSubscriber($this->cartEnqueuer, new NullLogger());
    }

    public function test_get_subscribed_events(): void
    {
        $events = CartSubscriber::getSubscribedEvents();

        self::assertArrayHasKey(SyliusCartEvents::CART_CHANGE, $events);
        self::assertArrayHasKey(SyliusCartEvents::CART_ITEM_ADD, $events);
        self::assertArrayHasKey(SyliusCartEvents::CART_ITEM_REMOVE, $events);
        self::assertArrayHasKey(SyliusCartEvents::CART_CLEAR, $events);
        self::assertArrayNotHasKey('kernel.response', $events);
    }

    public function test_on_cart_change_enqueues_cart_immediately_when_id_is_set(): void
    {
        $order = new Order();
        self::setIdOnObject($order, 42);

        $this->cartEnqueuer->expects(self::once())->method('enqueue')->with($order);

        $this->subscriber->onCartChange(new GenericEvent($order));
    }

    public function test_on_cart_change_ignores_non_order_subject(): void
    {
        $this->cartEnqueuer->expects(self::never())->method('enqueue');

        $this->subscriber->onCartChange(new GenericEvent(new \stdClass()));
    }

    public function test_on_cart_item_add_enqueues_cart_immediately_when_id_is_set(): void
    {
        $order = new Order();
        self::setIdOnObject($order, 42);

        $command = $this->createMock(AddToCartCommandInterface::class);
        $command->method('getCart')->willReturn($order);

        $this->cartEnqueuer->expects(self::once())->method('enqueue')->with($order);

        $this->subscriber->onCartItemAdd(new GenericEvent($command));
    }

    public function test_on_cart_item_add_skips_new_cart_with_no_id(): void
    {
        $order = new Order();

        $command = $this->createMock(AddToCartCommandInterface::class);
        $command->method('getCart')->willReturn($order);

        $this->cartEnqueuer->expects(self::never())->method('enqueue');

        $this->subscriber->onCartItemAdd(new GenericEvent($command));
    }

    public function test_on_cart_item_add_ignores_non_command_subject(): void
    {
        $this->cartEnqueuer->expects(self::never())->method('enqueue');

        $this->subscriber->onCartItemAdd(new GenericEvent(new \stdClass()));
    }

    public function test_on_cart_item_remove_enqueues_cart_from_order_item(): void
    {
        $order = new Order();
        self::setIdOnObject($order, 42);

        $item = $this->createMock(OrderItemInterface::class);
        $item->method('getOrder')->willReturn($order);

        $this->cartEnqueuer->expects(self::once())->method('enqueue')->with($order);

        $this->subscriber->onCartItemRemove(new GenericEvent($item));
    }

    public function test_on_cart_item_remove_ignores_non_item_subject(): void
    {
        $this->cartEnqueuer->expects(self::never())->method('enqueue');

        $this->subscriber->onCartItemRemove(new GenericEvent(new \stdClass()));
    }

    public function test_on_cart_item_remove_ignores_item_with_no_order(): void
    {
        $item = $this->createMock(OrderItemInterface::class);
        $item->method('getOrder')->willReturn(null);

        $this->cartEnqueuer->expects(self::never())->method('enqueue');

        $this->subscriber->onCartItemRemove(new GenericEvent($item));
    }

    public function test_on_cart_clear_enqueues_removal(): void
    {
        $order = new Order();
        self::setIdOnObject($order, 42);

        $this->cartEnqueuer->expects(self::once())->method('enqueueRemoval')->with($order);

        $this->subscriber->onCartClear(new GenericEvent($order));
    }

    public function test_on_cart_clear_ignores_non_order_subject(): void
    {
        $this->cartEnqueuer->expects(self::never())->method('enqueueRemoval');

        $this->subscriber->onCartClear(new GenericEvent(new \stdClass()));
    }
}
