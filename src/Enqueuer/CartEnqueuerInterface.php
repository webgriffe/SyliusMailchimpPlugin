<?php

declare(strict_types=1);

namespace Webgriffe\SyliusMailchimpPlugin\Enqueuer;

use Sylius\Component\Core\Model\OrderInterface;

interface CartEnqueuerInterface
{
    public function enqueue(OrderInterface $order): void;

    public function enqueueRemoval(OrderInterface $order): void;
}
