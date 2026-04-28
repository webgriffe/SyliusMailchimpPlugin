<?php

declare(strict_types=1);

namespace Webgriffe\SyliusMailchimpPlugin\Message\Order;

final class OrderRemove
{
    public function __construct(
        public readonly string $storeId,
        public readonly string $orderId,
    ) {
    }
}
