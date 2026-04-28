<?php

declare(strict_types=1);

namespace Webgriffe\SyliusMailchimpPlugin\Message\Cart;

final class CartRemove
{
    public function __construct(
        public readonly string $storeId,
        public readonly string $cartId,
    ) {
    }
}
