<?php

declare(strict_types=1);

namespace Webgriffe\SyliusMailchimpPlugin\Mapper;

use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\ProductVariantInterface;

interface ProductVariantMapperInterface
{
    /** @return array<string, mixed> */
    public function map(ProductVariantInterface $variant, ChannelInterface $channel, string $productUrl): array;
}
