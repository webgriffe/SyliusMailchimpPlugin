<?php

declare(strict_types=1);

namespace Webgriffe\SyliusMailchimpPlugin\ValueObject;

final class Cart
{
    /** @param CartLine[] $lines */
    public function __construct(
        public readonly string $id,
        public readonly EcommerceCustomer $customer,
        public readonly string $checkoutUrl,
        public readonly string $currencyCode,
        public readonly float $orderTotal,
        public readonly array $lines = [],
    ) {
    }
}
