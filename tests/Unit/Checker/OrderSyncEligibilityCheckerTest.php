<?php

declare(strict_types=1);

namespace Tests\Webgriffe\SyliusMailchimpPlugin\Unit\Checker;

use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\OrderCheckoutStates;
use Sylius\Component\Core\OrderPaymentStates;
use Sylius\Component\Order\Model\OrderInterface;
use Tests\Webgriffe\SyliusMailchimpPlugin\Entity\Order\Order;
use Webgriffe\SyliusMailchimpPlugin\Checker\OrderSyncEligibilityChecker;

final class OrderSyncEligibilityCheckerTest extends TestCase
{
    public function test_cart_state_is_syncable_as_cart(): void
    {
        $order = $this->createOrder(OrderInterface::STATE_CART, OrderCheckoutStates::STATE_CART, OrderPaymentStates::STATE_CART);

        self::assertTrue((new OrderSyncEligibilityChecker(false))->isSyncableAsCart($order));
    }

    public function test_completed_order_is_not_syncable_as_cart(): void
    {
        $order = $this->createOrder(OrderInterface::STATE_NEW, OrderCheckoutStates::STATE_COMPLETED, OrderPaymentStates::STATE_PAID);

        self::assertFalse((new OrderSyncEligibilityChecker(false))->isSyncableAsCart($order));
        self::assertFalse((new OrderSyncEligibilityChecker(true))->isSyncableAsCart($order));
    }

    public function test_unpaid_new_order_is_syncable_as_cart_only_when_enabled(): void
    {
        $order = $this->createOrder(OrderInterface::STATE_NEW, OrderCheckoutStates::STATE_COMPLETED, OrderPaymentStates::STATE_AWAITING_PAYMENT);

        self::assertTrue((new OrderSyncEligibilityChecker(true))->isSyncableAsCart($order));
        self::assertFalse((new OrderSyncEligibilityChecker(false))->isSyncableAsCart($order));
    }

    public function test_paid_new_order_is_not_syncable_as_cart(): void
    {
        $order = $this->createOrder(OrderInterface::STATE_NEW, OrderCheckoutStates::STATE_COMPLETED, OrderPaymentStates::STATE_PAID);

        self::assertFalse((new OrderSyncEligibilityChecker(true))->isSyncableAsCart($order));
    }

    public function test_completed_checkout_is_syncable_as_order(): void
    {
        $order = $this->createOrder(OrderInterface::STATE_NEW, OrderCheckoutStates::STATE_COMPLETED, OrderPaymentStates::STATE_PAID);

        self::assertTrue((new OrderSyncEligibilityChecker(false))->isSyncableAsOrder($order));
    }

    public function test_cart_is_not_syncable_as_order(): void
    {
        $order = $this->createOrder(OrderInterface::STATE_CART, OrderCheckoutStates::STATE_CART, OrderPaymentStates::STATE_CART);

        self::assertFalse((new OrderSyncEligibilityChecker(false))->isSyncableAsOrder($order));
    }

    private function createOrder(string $state, string $checkoutState, string $paymentState): Order
    {
        $order = new Order();
        $order->setState($state);
        $order->setCheckoutState($checkoutState);
        $order->setPaymentState($paymentState);

        return $order;
    }
}
