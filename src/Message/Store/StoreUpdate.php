<?php

declare(strict_types=1);

namespace Webgriffe\SyliusMailchimpPlugin\Message\Store;

final class StoreUpdate
{
    public function __construct(
        public readonly int $channelId,
    ) {
    }
}
