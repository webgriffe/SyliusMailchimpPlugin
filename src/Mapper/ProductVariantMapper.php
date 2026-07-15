<?php

declare(strict_types=1);

namespace Webgriffe\SyliusMailchimpPlugin\Mapper;

use Liip\ImagineBundle\Service\FilterService;
use Sylius\Component\Core\Calculator\ProductVariantPricesCalculatorInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\ProductImageInterface;
use Sylius\Component\Core\Model\ProductVariantInterface;
use Webgriffe\SyliusMailchimpPlugin\Util\IdSanitizer;

final class ProductVariantMapper implements ProductVariantMapperInterface
{
    public function __construct(
        private readonly ProductVariantPricesCalculatorInterface $pricesCalculator,
        private readonly FilterService $imagineFilterService,
    ) {
    }

    #[\Override]
    public function map(ProductVariantInterface $variant, ChannelInterface $channel, string $productUrl): array
    {
        $variantId = IdSanitizer::sanitize($variant->getCode() ?? '');
        $price = round($this->pricesCalculator->calculate($variant, ['channel' => $channel]) / 100, 2);

        $inventoryQuantity = 0;
        if ($variant->isTracked()) {
            $inventoryQuantity = max(0, (int) $variant->getOnHand() - (int) $variant->getOnHold());
        }

        $payload = [
            'id' => $variantId,
            'title' => $variant->getDescriptor(),
            'url' => $productUrl,
            'sku' => $variant->getCode() ?? '',
            'price' => $price,
            'inventory_quantity' => $inventoryQuantity,
        ];

        $firstImage = $variant->getImages()->first();
        if ($firstImage instanceof ProductImageInterface) {
            $path = $firstImage->getPath();
            if ($path !== null && $path !== '') {
                $payload['image_url'] = $this->imagineFilterService->getUrlOfFilteredImage($path, 'sylius_shop_product_large_thumbnail');
            }
        }

        return $payload;
    }
}
