<?php

declare(strict_types=1);

namespace Webgriffe\SyliusMailchimpPlugin\Mapper;

use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\ProductInterface;

interface ProductMapperInterface
{
    /** @return array<string, mixed> */
    public function map(ProductInterface $product, ChannelInterface $channel, string $locale): array;
}
