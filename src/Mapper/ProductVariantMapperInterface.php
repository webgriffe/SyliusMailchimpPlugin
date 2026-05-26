<?php

declare(strict_types=1);

namespace Webgriffe\SyliusMailchimpPlugin\Mapper;

use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\ProductVariantInterface;
use Webgriffe\SyliusMailchimpPlugin\ValueObject\ProductVariant;

interface ProductVariantMapperInterface
{
    public function map(ProductVariantInterface $variant, ChannelInterface $channel, string $productUrl): ProductVariant;
}
