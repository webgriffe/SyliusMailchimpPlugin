<?php

declare(strict_types=1);

namespace Webgriffe\SyliusMailchimpPlugin\Message\Order;

final class OrderCreate
{
    public function __construct(
        public readonly int $orderId,
        public readonly int $channelId,
        public readonly bool $isInRealTime = false,
    ) {
    }
}
