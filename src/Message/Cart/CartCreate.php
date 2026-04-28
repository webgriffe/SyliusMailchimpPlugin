<?php

declare(strict_types=1);

namespace Webgriffe\SyliusMailchimpPlugin\Message\Cart;

final class CartCreate
{
    public function __construct(
        public readonly int $orderId,
        public readonly int $channelId,
    ) {
    }
}
