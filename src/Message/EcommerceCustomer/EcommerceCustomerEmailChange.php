<?php

declare(strict_types=1);

namespace Webgriffe\SyliusMailchimpPlugin\Message\EcommerceCustomer;

final class EcommerceCustomerEmailChange
{
    public function __construct(
        public readonly int $customerId,
    ) {
    }
}
