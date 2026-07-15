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

final class ProductMapper implements ProductMapperInterface
{
    public function __construct(
        private readonly ProductVariantMapperInterface $productVariantMapper,
        private readonly UrlGeneratorInterface $router,
        private readonly FilterService $imagineFilterService,
    ) {
    }

    #[\Override]
    public function map(ProductInterface $product, ChannelInterface $channel, string $locale): array
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

        $variants = [];
        foreach ($product->getVariants() as $variant) {
            if ($variant instanceof ProductVariantInterface) {
                $variants[] = $this->productVariantMapper->map($variant, $channel, $url);
            }
        }

        $payload = [
            'id' => $productId,
            'title' => (string) $translation->getName(),
            'url' => $url,
            'description' => (string) $translation->getDescription(),
            'type' => '',
            'vendor' => '',
            'variants' => $variants,
        ];

        $firstImage = $product->getImages()->first();
        if ($firstImage instanceof ProductImageInterface) {
            $path = $firstImage->getPath();
            if ($path !== null && $path !== '') {
                $payload['image_url'] = $this->imagineFilterService->getUrlOfFilteredImage($path, 'sylius_shop_product_large_thumbnail');
            }
        }

        return $payload;
    }
}
