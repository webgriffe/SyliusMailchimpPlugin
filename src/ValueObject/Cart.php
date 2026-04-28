<?php

declare(strict_types=1);

namespace Webgriffe\SyliusMailchimpPlugin\ValueObject;

/** Placeholder — full implementation in Fase 2 (Commit 15). */
final class Cart
{
    public function __construct(
        public readonly string $id,
        public readonly string $customerId,
        public readonly string $checkoutUrl,
        public readonly string $currencyCode,
        public readonly float $orderTotal,
    ) {
    }
}
