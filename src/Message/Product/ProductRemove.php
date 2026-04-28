<?php

declare(strict_types=1);

namespace Webgriffe\SyliusMailchimpPlugin\Message\Product;

final class ProductRemove
{
    public function __construct(
        public readonly string $storeId,
        public readonly string $productId,
    ) {
    }
}
