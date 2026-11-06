<?php

declare(strict_types=1);

namespace Webgriffe\SyliusMailchimpPlugin\Checker;

use Sylius\Component\Core\Model\OrderInterface;

interface OrderSyncEligibilityCheckerInterface
{
    public function isSyncableAsCart(OrderInterface $order): bool;

    public function isSyncableAsOrder(OrderInterface $order): bool;
}
