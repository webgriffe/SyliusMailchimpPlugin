<?php

declare(strict_types=1);

namespace Webgriffe\SyliusMailchimpPlugin\ValueObject;

final class Order
{
    /** @param OrderLine[] $lines */
    public function __construct(
        public readonly string $id,
        public readonly EcommerceCustomer $customer,
        public readonly string $currencyCode,
        public readonly float $orderTotal,
        public readonly array $lines = [],
        public readonly float $taxTotal = 0.0,
        public readonly float $shippingTotal = 0.0,
        public readonly float $discountTotal = 0.0,
        public readonly ?Address $billingAddress = null,
        public readonly ?Address $shippingAddress = null,
        public readonly ?\DateTimeInterface $processedAt = null,
        public readonly bool $isInRealTime = false,
        public readonly ?string $cartId = null,
    ) {
    }
}
