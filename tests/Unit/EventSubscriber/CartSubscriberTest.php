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
use Webgriffe\SyliusMailchimpPlugin\Enqueuer\CartEnqueuerInterface;
use Webgriffe\SyliusMailchimpPlugin\EventSubscriber\CartSubscriber;

final class CartSubscriberTest extends TestCase
{
    private MockObject&CartEnqueuerInterface $cartEnqueuer;

    private CartSubscriber $subscriber;

    protected function setUp(): void
    {
        $this->cartEnqueuer = $this->createMock(CartEnqueuerInterface::class);
        $this->subscriber = new CartSubscriber($this->cartEnqueuer, new NullLogger());
    }

    public function testGetSubscribedEvents(): void
    {
        $events = CartSubscriber::getSubscribedEvents();

        self::assertArrayHasKey(SyliusCartEvents::CART_CHANGE, $events);
        self::assertArrayHasKey(SyliusCartEvents::CART_ITEM_ADD, $events);
        self::assertArrayHasKey(SyliusCartEvents::CART_ITEM_REMOVE, $events);
        self::assertArrayHasKey(SyliusCartEvents::CART_CLEAR, $events);
        self::assertArrayNotHasKey('kernel.response', $events);
    }

    public function testOnCartChangeEnqueuesCartImmediatelyWhenIdIsSet(): void
    {
        $order = new Order();
        $this->setId($order, 42);

        $this->cartEnqueuer->expects(self::once())->method('enqueue')->with($order);

        $this->subscriber->onCartChange(new GenericEvent($order));
    }

    public function testOnCartChangeIgnoresNonOrderSubject(): void
    {
        $this->cartEnqueuer->expects(self::never())->method('enqueue');

        $this->subscriber->onCartChange(new GenericEvent(new \stdClass()));
    }

    public function testOnCartItemAddEnqueuesCartImmediatelyWhenIdIsSet(): void
    {
        $order = new Order();
        $this->setId($order, 42);

        $command = $this->createMock(AddToCartCommandInterface::class);
        $command->method('getCart')->willReturn($order);

        $this->cartEnqueuer->expects(self::once())->method('enqueue')->with($order);

        $this->subscriber->onCartItemAdd(new GenericEvent($command));
    }

    public function testOnCartItemAddSkipsNewCartWithNoId(): void
    {
        $order = new Order();

        $command = $this->createMock(AddToCartCommandInterface::class);
        $command->method('getCart')->willReturn($order);

        $this->cartEnqueuer->expects(self::never())->method('enqueue');

        $this->subscriber->onCartItemAdd(new GenericEvent($command));
    }

    public function testOnCartItemAddIgnoresNonCommandSubject(): void
    {
        $this->cartEnqueuer->expects(self::never())->method('enqueue');

        $this->subscriber->onCartItemAdd(new GenericEvent(new \stdClass()));
    }

    public function testOnCartItemRemoveEnqueuesCartFromOrderItem(): void
    {
        $order = new Order();
        $this->setId($order, 42);

        $item = $this->createMock(OrderItemInterface::class);
        $item->method('getOrder')->willReturn($order);

        $this->cartEnqueuer->expects(self::once())->method('enqueue')->with($order);

        $this->subscriber->onCartItemRemove(new GenericEvent($item));
    }

    public function testOnCartItemRemoveIgnoresNonItemSubject(): void
    {
        $this->cartEnqueuer->expects(self::never())->method('enqueue');

        $this->subscriber->onCartItemRemove(new GenericEvent(new \stdClass()));
    }

    public function testOnCartItemRemoveIgnoresItemWithNoOrder(): void
    {
        $item = $this->createMock(OrderItemInterface::class);
        $item->method('getOrder')->willReturn(null);

        $this->cartEnqueuer->expects(self::never())->method('enqueue');

        $this->subscriber->onCartItemRemove(new GenericEvent($item));
    }

    public function testOnCartClearEnqueuesRemoval(): void
    {
        $order = new Order();
        $this->setId($order, 42);

        $this->cartEnqueuer->expects(self::once())->method('enqueueRemoval')->with($order);

        $this->subscriber->onCartClear(new GenericEvent($order));
    }

    public function testOnCartClearIgnoresNonOrderSubject(): void
    {
        $this->cartEnqueuer->expects(self::never())->method('enqueueRemoval');

        $this->subscriber->onCartClear(new GenericEvent(new \stdClass()));
    }

    private function setId(Order $order, int $id): void
    {
        $ref = new \ReflectionProperty($order, 'id');
        $ref->setValue($order, $id);
    }
}
