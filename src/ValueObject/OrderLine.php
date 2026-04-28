<?php

declare(strict_types=1);

namespace Webgriffe\SyliusMailchimpPlugin\ValueObject;

final class OrderLine
{
    public function __construct(
        public readonly string $id,
        public readonly string $productId,
        public readonly string $productVariantId,
        public readonly int $quantity,
        public readonly float $price,
        public readonly float $discount = 0.0,
    ) {
    }
}
