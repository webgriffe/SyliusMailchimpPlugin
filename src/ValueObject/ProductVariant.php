<?php

declare(strict_types=1);

namespace Webgriffe\SyliusMailchimpPlugin\ValueObject;

final class ProductVariant
{
    public function __construct(
        public readonly string $id,
        public readonly string $title,
        public readonly string $url,
        public readonly string $sku = '',
        public readonly float $price = 0.0,
        public readonly int $inventoryQuantity = 0,
        public readonly string $imageUrl = '',
    ) {
    }
}
