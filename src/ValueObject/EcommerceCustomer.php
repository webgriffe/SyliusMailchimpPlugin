<?php

declare(strict_types=1);

namespace Webgriffe\SyliusMailchimpPlugin\ValueObject;

/** Placeholder — full implementation in Fase 2 (Commit 15). */
final class EcommerceCustomer
{
    public function __construct(
        public readonly string $id,
        public readonly string $emailAddress,
        public readonly string $firstName = '',
        public readonly string $lastName = '',
    ) {
    }
}
