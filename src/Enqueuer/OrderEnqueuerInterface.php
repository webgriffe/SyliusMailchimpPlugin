<?php

declare(strict_types=1);

namespace Webgriffe\SyliusMailchimpPlugin\Enqueuer;

use Sylius\Component\Core\Model\OrderInterface;

interface OrderEnqueuerInterface
{
    public function enqueue(OrderInterface $order, bool $isInRealTime = false): void;

    public function enqueueRemoval(OrderInterface $order): void;
}
