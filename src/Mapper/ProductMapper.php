<?php

declare(strict_types=1);

namespace Webgriffe\SyliusMailchimpPlugin\Mapper;

use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Model\ProductVariantInterface;
use Webgriffe\SyliusMailchimpPlugin\Util\IdSanitizer;
use Webgriffe\SyliusMailchimpPlugin\ValueObject\Product;

final class ProductMapper
{
    public function __construct(
        private readonly ProductVariantMapper $productVariantMapper,
    ) {
    }

    public function map(ProductInterface $product, ChannelInterface $channel, string $locale): Product
    {
        $translation = $product->getTranslation($locale);
        $productId = IdSanitizer::sanitize((string) $product->getCode());
        $channelHostname = rtrim((string) $channel->getHostname(), '/');
        $slug = $translation->getSlug() ?? '';
        $url = $slug !== '' ? sprintf('%s/products/%s', $channelHostname, $slug) : $channelHostname;

        $variants = [];
        foreach ($product->getVariants() as $variant) {
            if ($variant instanceof ProductVariantInterface) {
                $variants[] = $this->productVariantMapper->map($variant, $channel, $url);
            }
        }

        return new Product(
            id: $productId,
            title: (string) $translation->getName(),
            url: $url,
            variants: $variants,
            description: (string) $translation->getDescription(),
        );
    }
}
