<?php

declare(strict_types=1);

namespace Webgriffe\SyliusMailchimpPlugin\Message\Product;

final class ProductCreate
{
    public function __construct(
        public readonly int $productId,
        public readonly int $channelId,
        public readonly string $locale,
    ) {
    }
}
