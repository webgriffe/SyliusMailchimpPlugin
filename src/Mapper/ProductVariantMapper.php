<?php

declare(strict_types=1);

namespace Webgriffe\SyliusMailchimpPlugin\Mapper;

use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\ProductVariantInterface;
use Webgriffe\SyliusMailchimpPlugin\Util\IdSanitizer;
use Webgriffe\SyliusMailchimpPlugin\ValueObject\ProductVariant;

final class ProductVariantMapper
{
    public function map(ProductVariantInterface $variant, ChannelInterface $channel, string $productUrl): ProductVariant
    {
        $variantId = IdSanitizer::sanitize(sprintf('%s_%s', $variant->getProduct()?->getCode() ?? '', $variant->getCode() ?? ''));
        $channelPricing = $variant->getChannelPricingForChannel($channel);
        $priceInCents = $channelPricing?->getPrice() ?? 0;
        $price = round($priceInCents / 100, 2);

        return new ProductVariant(
            id: $variantId,
            title: $variant->getDescriptor(),
            url: $productUrl,
            sku: $variant->getCode() ?? '',
            price: $price,
        );
    }
}
