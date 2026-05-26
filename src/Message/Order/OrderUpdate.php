<?php

declare(strict_types=1);

namespace Webgriffe\SyliusMailchimpPlugin\Message\Order;

final class OrderUpdate
{
    public function __construct(
        public readonly int $orderId,
    ) {
    }
}
