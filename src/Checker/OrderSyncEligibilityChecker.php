<?php

declare(strict_types=1);

namespace Webgriffe\SyliusMailchimpPlugin\Checker;

use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\OrderCheckoutStates;
use Sylius\Component\Core\OrderPaymentStates;
use Sylius\Component\Order\Model\OrderInterface as BaseOrderInterface;

final class OrderSyncEligibilityChecker implements OrderSyncEligibilityCheckerInterface
{
    public function __construct(private readonly bool $sendUnpaidOrdersAsCarts)
    {
    }

    #[\Override]
    public function isSyncableAsCart(OrderInterface $order): bool
    {
        if ($order->getState() === BaseOrderInterface::STATE_CART) {
            return true;
        }

        return $this->sendUnpaidOrdersAsCarts &&
            $order->getState() === BaseOrderInterface::STATE_NEW &&
            $order->getPaymentState() === OrderPaymentStates::STATE_AWAITING_PAYMENT;
    }

    #[\Override]
    public function isSyncableAsOrder(OrderInterface $order): bool
    {
        return $order->getCheckoutState() === OrderCheckoutStates::STATE_COMPLETED;
    }
}
