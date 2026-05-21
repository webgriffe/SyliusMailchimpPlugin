<?php

declare(strict_types=1);

namespace Webgriffe\SyliusMailchimpPlugin\Mapper;

use Sylius\Component\Core\Model\AddressInterface;
use Sylius\Component\Core\Model\AdjustmentInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\OrderItemInterface;
use Sylius\Component\Core\Model\ProductVariantInterface;
use Webgriffe\SyliusMailchimpPlugin\Model\MailchimpOrderAwareInterface;
use Webgriffe\SyliusMailchimpPlugin\Util\IdSanitizer;
use Webgriffe\SyliusMailchimpPlugin\ValueObject\Address;
use Webgriffe\SyliusMailchimpPlugin\ValueObject\Order;
use Webgriffe\SyliusMailchimpPlugin\ValueObject\OrderLine;

final class OrderMapper
{
    public function __construct(
        private readonly EcommerceCustomerMapper $customerMapper,
    ) {
    }

    public function map(OrderInterface $order, bool $isInRealTime = false): Order
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

        return new Order(
            id: $orderId,
            customer: $this->customerMapper->mapFromOrder($order),
            currencyCode: $currencyCode,
            orderTotal: $orderTotal,
            lines: $lines,
            taxTotal: $taxTotal,
            shippingTotal: $shippingTotal,
            discountTotal: $discountTotal,
            billingAddress: $this->mapAddress($order->getBillingAddress()),
            shippingAddress: $this->mapAddress($order->getShippingAddress()),
            processedAt: $order->getCheckoutCompletedAt(),
            isInRealTime: $isInRealTime,
            cartId: $order instanceof MailchimpOrderAwareInterface ? $order->getMailchimpCartId() : null,
        );
    }

    private function mapOrderLine(OrderItemInterface $item): ?OrderLine
    {
        $variant = $item->getVariant();
        if (!$variant instanceof ProductVariantInterface) {
            return null;
        }

        $lineId = IdSanitizer::sanitize(sprintf('line-%s', (string) $item->getId()));
        $productId = IdSanitizer::sanitize($variant->getProduct()?->getCode() ?? '');
        $variantId = IdSanitizer::sanitize($variant->getCode() ?? '');
        $discount = abs(round($item->getAdjustmentsTotalRecursively(AdjustmentInterface::ORDER_PROMOTION_ADJUSTMENT) / 100, 2));

        return new OrderLine(
            id: $lineId,
            productId: $productId,
            productVariantId: $variantId,
            quantity: $item->getQuantity(),
            price: round($item->getUnitPrice() / 100, 2),
            discount: $discount,
        );
    }

    private function mapAddress(?AddressInterface $address): ?Address
    {
        if ($address === null) {
            return null;
        }

        return new Address(
            name: trim(sprintf('%s %s', $address->getFirstName() ?? '', $address->getLastName() ?? '')),
            address1: (string) $address->getStreet(),
            city: (string) $address->getCity(),
            postalCode: (string) $address->getPostcode(),
            country: (string) $address->getCountryCode(),
            countryCode: (string) $address->getCountryCode(),
            province: (string) $address->getProvinceName(),
            provinceCode: (string) $address->getProvinceCode(),
        );
    }
}
