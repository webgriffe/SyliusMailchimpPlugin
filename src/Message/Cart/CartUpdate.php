<?php

declare(strict_types=1);

namespace Webgriffe\SyliusMailchimpPlugin\Message\Cart;

final class CartUpdate
{
    public function __construct(
        public readonly int $orderId,
        public readonly int $channelId,
    ) {
    }
}
