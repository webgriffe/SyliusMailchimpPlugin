<?php

declare(strict_types=1);

namespace Webgriffe\SyliusMailchimpPlugin\Mapper;

use Liip\ImagineBundle\Service\FilterService;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\ProductImageInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Model\ProductVariantInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Webgriffe\SyliusMailchimpPlugin\Util\IdSanitizer;
use Webgriffe\SyliusMailchimpPlugin\ValueObject\Product;

final class ProductMapper
{
    public function __construct(
        private readonly ProductVariantMapper $productVariantMapper,
        private readonly UrlGeneratorInterface $router,
        private readonly FilterService $imagineFilterService,
    ) {
    }

    public function map(ProductInterface $product, ChannelInterface $channel, string $locale): Product
    {
        $translation = $product->getTranslation($locale);
        $productId = IdSanitizer::sanitize((string) $product->getCode());
        $slug = $translation->getSlug() ?? '';
        $url = $slug !== ''
            ? $this->router->generate(
                'sylius_shop_product_show',
                ['slug' => $slug, '_locale' => $locale],
                UrlGeneratorInterface::ABSOLUTE_URL,
            )
            : (string) $channel->getHostname();

        $imageUrl = '';
        $firstImage = $product->getImages()->first();
        if ($firstImage instanceof ProductImageInterface) {
            $path = $firstImage->getPath();
            if ($path !== null && $path !== '') {
                $imageUrl = $this->imagineFilterService->getUrlOfFilteredImage($path, 'sylius_shop_product_large_thumbnail');
            }
        }

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
            imageUrl: $imageUrl,
        );
    }
}
