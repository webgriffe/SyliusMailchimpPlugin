<?php

declare(strict_types=1);

namespace Webgriffe\SyliusMailchimpPlugin\Mapper;

use DateTimeInterface;
use Sylius\Component\Core\Model\AdjustmentInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\OrderItemInterface;
use Sylius\Component\Core\Model\ProductVariantInterface;
use Webgriffe\SyliusMailchimpPlugin\Model\MailchimpOrderAwareInterface;
use Webgriffe\SyliusMailchimpPlugin\Util\IdSanitizer;

final class OrderMapper implements OrderMapperInterface
{
    public function __construct(
        private readonly EcommerceCustomerMapperInterface $customerMapper,
    ) {
    }

    #[\Override]
    public function map(OrderInterface $order, bool $isInRealTime = false): array
    {
        $orderId = IdSanitizer::sanitize((string) $order->getId());
        $currencyCode = (string) $order->getCurrencyCode();
        $orderTotal = round($order->getTotal() / 100, 2);
        $taxTotal = round($order->getTaxTotal() / 100, 2);
        $shippingTotal = round($order->getShippingTotal() / 100, 2);
        $discountTotal = abs(round($order->getAdjustmentsTotalRecursively(AdjustmentInterface::ORDER_PROMOTION_ADJUSTMENT) / 100, 2));

        $lines = [];
        foreach ($order->getItems() as $item) {
            $line = $this->mapOrderLine($item);
            if ($line !== null) {
                $lines[] = $line;
            }
        }

        $payload = [
            'id' => $orderId,
            'customer' => $this->customerMapper->mapFromOrder($order),
            'currency_code' => $currencyCode,
            'order_total' => $orderTotal,
            'tax_total' => $taxTotal,
            'shipping_total' => $shippingTotal,
            'discount_total' => $discountTotal,
            'lines' => $lines,
        ];

        $processedAt = $order->getCheckoutCompletedAt();
        if ($processedAt !== null) {
            $payload['processed_at_foreign'] = $processedAt->format(DateTimeInterface::ATOM);
        }

        if ($order instanceof MailchimpOrderAwareInterface && $order->getMailchimpCartId() !== null) {
            $payload['cart_id'] = $order->getMailchimpCartId();
        }

        return $payload;
    }

    /** @return array<string, mixed>|null */
    private function mapOrderLine(OrderItemInterface $item): ?array
    {
        $variant = $item->getVariant();
        if (!$variant instanceof ProductVariantInterface) {
            return null;
        }

        $lineId = IdSanitizer::sanitize(sprintf('line-%s', (string) $item->getId()));
        $productId = IdSanitizer::sanitize($variant->getProduct()?->getCode() ?? '');
        $variantId = IdSanitizer::sanitize($variant->getCode() ?? '');
        $discount = abs(round($item->getAdjustmentsTotalRecursively(AdjustmentInterface::ORDER_PROMOTION_ADJUSTMENT) / 100, 2));

        return [
            'id' => $lineId,
            'product_id' => $productId,
            'product_variant_id' => $variantId,
            'quantity' => $item->getQuantity(),
            'price' => round($item->getUnitPrice() / 100, 2),
            'discount' => $discount,
        ];
    }
}
