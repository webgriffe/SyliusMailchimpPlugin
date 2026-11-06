<?php

declare(strict_types=1);

namespace Webgriffe\SyliusMailchimpPlugin\Mapper;

use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\OrderItemInterface;
use Sylius\Component\Core\Model\ProductVariantInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Webgriffe\SyliusMailchimpPlugin\Model\ChannelMailchimpAwareInterface;
use Webgriffe\SyliusMailchimpPlugin\Util\IdSanitizer;

final class CartMapper implements CartMapperInterface
{
    public function __construct(
        private readonly EcommerceCustomerMapperInterface $customerMapper,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    #[\Override]
    public function map(OrderInterface $order, ChannelInterface&ChannelMailchimpAwareInterface $channel): array
    {
        $cartId = IdSanitizer::sanitize((string) $order->getId());
        $checkoutUrl = $this->buildCheckoutUrl($order, $channel);
        $currencyCode = (string) $order->getCurrencyCode();
        $orderTotal = round($order->getTotal() / 100, 2);

        $lines = [];
        foreach ($order->getItems() as $item) {
            $line = $this->mapCartLine($item);
            if ($line !== null) {
                $lines[] = $line;
            }
        }

        return [
            'id' => $cartId,
            'customer' => $this->customerMapper->mapFromOrder($order),
            'checkout_url' => $checkoutUrl,
            'currency_code' => $currencyCode,
            'order_total' => $orderTotal,
            'lines' => $lines,
        ];
    }

    private function buildCheckoutUrl(OrderInterface $order, ChannelInterface $channel): string
    {
        $parameters = ['tokenValue' => $order->getTokenValue()];
        if ($order->getLocaleCode() !== null) {
            $parameters['_locale'] = $order->getLocaleCode();
        }

        $hostname = rtrim((string) $channel->getHostname(), '/');
        if ($hostname === '') {
            return $this->urlGenerator->generate(
                'webgriffe_sylius_mailchimp_cart_restore',
                $parameters,
                UrlGeneratorInterface::ABSOLUTE_URL,
            );
        }

        if (!str_contains($hostname, '://')) {
            $hostname = 'https://' . $hostname;
        }

        return $hostname . $this->urlGenerator->generate(
            'webgriffe_sylius_mailchimp_cart_restore',
            $parameters,
            UrlGeneratorInterface::ABSOLUTE_PATH,
        );
    }

    /** @return array<string, mixed>|null */
    private function mapCartLine(OrderItemInterface $item): ?array
    {
        $variant = $item->getVariant();
        if (!$variant instanceof ProductVariantInterface) {
            return null;
        }

        $lineId = IdSanitizer::sanitize(sprintf('%s_%s', (string) $item->getId(), $variant->getCode() ?? ''));
        $productId = IdSanitizer::sanitize($variant->getProduct()?->getCode() ?? '');
        $variantId = IdSanitizer::sanitize($variant->getCode() ?? '');

        return [
            'id' => $lineId,
            'product_id' => $productId,
            'product_variant_id' => $variantId,
            'quantity' => $item->getQuantity(),
            'price' => round($item->getUnitPrice() / 100, 2),
        ];
    }
}
