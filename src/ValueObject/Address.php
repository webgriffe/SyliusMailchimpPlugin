<?php

declare(strict_types=1);

namespace Webgriffe\SyliusMailchimpPlugin\ValueObject;

final class Address
{
    public function __construct(
        public readonly string $name = '',
        public readonly string $address1 = '',
        public readonly string $address2 = '',
        public readonly string $city = '',
        public readonly string $province = '',
        public readonly string $provinceCode = '',
        public readonly string $postalCode = '',
        public readonly string $country = '',
        public readonly string $countryCode = '',
        public readonly string $phone = '',
    ) {
    }
}
