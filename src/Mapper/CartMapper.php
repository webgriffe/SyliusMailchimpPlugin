<?php

declare(strict_types=1);

namespace Webgriffe\SyliusMailchimpPlugin\Mapper;

use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\OrderItemInterface;
use Sylius\Component\Core\Model\ProductVariantInterface;
use Webgriffe\SyliusMailchimpPlugin\Model\ChannelMailchimpAwareInterface;
use Webgriffe\SyliusMailchimpPlugin\Util\IdSanitizer;
use Webgriffe\SyliusMailchimpPlugin\ValueObject\Cart;
use Webgriffe\SyliusMailchimpPlugin\ValueObject\CartLine;

final class CartMapper
{
    public function __construct(
        private readonly EcommerceCustomerMapper $customerMapper,
    ) {
    }

    public function map(OrderInterface $order, ChannelInterface&ChannelMailchimpAwareInterface $channel): Cart
    {
        $cartId = IdSanitizer::sanitize((string) $order->getId());
        $checkoutUrl = sprintf('%s/checkout', rtrim((string) $channel->getHostname(), '/'));
        $currencyCode = (string) $order->getCurrencyCode();
        $orderTotal = round($order->getTotal() / 100, 2);

        $lines = [];
        foreach ($order->getItems() as $item) {
            $line = $this->mapCartLine($item);
            if ($line !== null) {
                $lines[] = $line;
            }
        }

        return new Cart(
            id: $cartId,
            customer: $this->customerMapper->mapFromOrder($order),
            checkoutUrl: $checkoutUrl,
            currencyCode: $currencyCode,
            orderTotal: $orderTotal,
            lines: $lines,
        );
    }

    private function mapCartLine(OrderItemInterface $item): ?CartLine
    {
        $variant = $item->getVariant();
        if (!$variant instanceof ProductVariantInterface) {
            return null;
        }

        $lineId = IdSanitizer::sanitize(sprintf('%s_%s', (string) $item->getId(), $variant->getCode() ?? ''));
        $productId = IdSanitizer::sanitize($variant->getProduct()?->getCode() ?? '');
        $variantId = IdSanitizer::sanitize($variant->getCode() ?? '');

        return new CartLine(
            id: $lineId,
            productId: $productId,
            productVariantId: $variantId,
            quantity: $item->getQuantity(),
            price: round($item->getUnitPrice() / 100, 2),
        );
    }
}
