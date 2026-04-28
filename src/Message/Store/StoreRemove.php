<?php

declare(strict_types=1);

namespace Webgriffe\SyliusMailchimpPlugin\Message\Store;

final class StoreRemove
{
    public function __construct(
        public readonly string $storeId,
    ) {
    }
}
