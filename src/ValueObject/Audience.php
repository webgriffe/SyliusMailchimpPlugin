<?php

declare(strict_types=1);

namespace Webgriffe\SyliusMailchimpPlugin\ValueObject;

use Sylius\Component\Core\Model\ChannelInterface;

final class Audience
{
    public function __construct(
        public readonly string $id,
        public readonly ChannelInterface $channel,
    ) {
    }
}
