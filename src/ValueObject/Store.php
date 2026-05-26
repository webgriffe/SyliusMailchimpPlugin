<?php

declare(strict_types=1);

namespace Webgriffe\SyliusMailchimpPlugin\ValueObject;

final class Store
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly string $domain,
        public readonly string $emailAddress,
        public readonly string $currencyCode,
        public readonly string $primaryLocale,
        public readonly string $listId = '',
        public readonly string $platform = 'Sylius',
        public readonly string $timezone = '',
        public readonly string $phone = '',
        public readonly string $address = '',
    ) {
    }
}
