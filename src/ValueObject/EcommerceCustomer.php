<?php

declare(strict_types=1);

namespace Webgriffe\SyliusMailchimpPlugin\ValueObject;

final class EcommerceCustomer
{
    public function __construct(
        public readonly string $id,
        public readonly string $emailAddress,
        public readonly string $firstName = '',
        public readonly string $lastName = '',
        public readonly ?Address $address = null,
        public readonly bool $optInStatus = false,
    ) {
    }
}
