<?php

declare(strict_types=1);

namespace Tests\Webgriffe\SyliusMailchimpPlugin\Unit\Mapper;

use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\AddressInterface;
use Sylius\Component\Core\Model\AdjustmentInterface;
use Sylius\Component\Core\Model\CustomerInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\OrderItemInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Model\ProductVariantInterface;
use Webgriffe\SyliusMailchimpPlugin\Mapper\EcommerceCustomerMapper;
use Webgriffe\SyliusMailchimpPlugin\Mapper\OrderMapper;

final class OrderMapperTest extends TestCase
{
    private OrderMapper $mapper;

    protected function setUp(): void
    {
        $this->mapper = new OrderMapper(new EcommerceCustomerMapper());
    }

    public function testMapsOrderToOrderVO(): void
    {
        $customer = $this->createMock(CustomerInterface::class);
        $customer->method('getId')->willReturn(1);
        $customer->method('getEmail')->willReturn('john@example.com');
        $customer->method('getFirstName')->willReturn('John');
        $customer->method('getLastName')->willReturn('Doe');
        $customer->method('isSubscribedToNewsletter')->willReturn(true);

        $product = $this->createMock(ProductInterface::class);
        $product->method('getCode')->willReturn('TSHIRT');

        $variant = $this->createMock(ProductVariantInterface::class);
        $variant->method('getCode')->willReturn('TSHIRT-L');
        $variant->method('getProduct')->willReturn($product);

        $item = $this->createMock(OrderItemInterface::class);
        $item->method('getId')->willReturn(5);
        $item->method('getVariant')->willReturn($variant);
        $item->method('getQuantity')->willReturn(1);
        $item->method('getUnitPrice')->willReturn(2999);
        $item->method('getAdjustmentsTotalRecursively')
            ->with(AdjustmentInterface::ORDER_PROMOTION_ADJUSTMENT)
            ->willReturn(-500);

        $billingAddress = $this->createMock(AddressInterface::class);
        $billingAddress->method('getFirstName')->willReturn('John');
        $billingAddress->method('getLastName')->willReturn('Doe');
        $billingAddress->method('getStreet')->willReturn('123 Main St');
        $billingAddress->method('getCity')->willReturn('New York');
        $billingAddress->method('getPostcode')->willReturn('10001');
        $billingAddress->method('getCountryCode')->willReturn('US');
        $billingAddress->method('getProvinceName')->willReturn('New York');
        $billingAddress->method('getProvinceCode')->willReturn('NY');

        $processedAt = new DateTimeImmutable('2024-01-15 10:00:00');

        $order = $this->createMock(OrderInterface::class);
        $order->method('getId')->willReturn(42);
        $order->method('getCustomer')->willReturn($customer);
        $order->method('getBillingAddress')->willReturn($billingAddress);
        $order->method('getShippingAddress')->willReturn(null);
        $order->method('getCurrencyCode')->willReturn('USD');
        $order->method('getTotal')->willReturn(2999);
        $order->method('getTaxTotal')->willReturn(300);
        $order->method('getShippingTotal')->willReturn(500);
        $order->method('getAdjustmentsTotalRecursively')
            ->with(AdjustmentInterface::ORDER_PROMOTION_ADJUSTMENT)
            ->willReturn(-200);
        $order->method('getItems')->willReturn(new ArrayCollection([$item]));
        $order->method('getCheckoutCompletedAt')->willReturn($processedAt);

        $mapped = $this->mapper->map($order);

        $this->assertSame('order-42', $mapped->id);
        $this->assertSame('john@example.com', $mapped->customer->emailAddress);
        $this->assertSame('USD', $mapped->currencyCode);
        $this->assertSame(29.99, $mapped->orderTotal);
        $this->assertSame(3.0, $mapped->taxTotal);
        $this->assertSame(5.0, $mapped->shippingTotal);
        $this->assertSame(2.0, $mapped->discountTotal);
        $this->assertCount(1, $mapped->lines);
        $this->assertSame('line-5', $mapped->lines[0]->id);
        $this->assertSame(5.0, $mapped->lines[0]->discount);
        $this->assertNotNull($mapped->billingAddress);
        $this->assertSame('John Doe', $mapped->billingAddress->name);
        $this->assertNull($mapped->shippingAddress);
        $this->assertSame($processedAt, $mapped->processedAt);
        $this->assertFalse($mapped->isInRealTime);
    }

    public function testMapsOrderWithIsInRealTimeFlag(): void
    {
        $customer = $this->createMock(CustomerInterface::class);
        $customer->method('getId')->willReturn(1);
        $customer->method('getEmail')->willReturn('x@example.com');
        $customer->method('getFirstName')->willReturn('');
        $customer->method('getLastName')->willReturn('');
        $customer->method('isSubscribedToNewsletter')->willReturn(false);

        $order = $this->createMock(OrderInterface::class);
        $order->method('getId')->willReturn(1);
        $order->method('getCustomer')->willReturn($customer);
        $order->method('getBillingAddress')->willReturn(null);
        $order->method('getShippingAddress')->willReturn(null);
        $order->method('getCurrencyCode')->willReturn('EUR');
        $order->method('getTotal')->willReturn(0);
        $order->method('getTaxTotal')->willReturn(0);
        $order->method('getShippingTotal')->willReturn(0);
        $order->method('getAdjustmentsTotalRecursively')->willReturn(0);
        $order->method('getItems')->willReturn(new ArrayCollection([]));
        $order->method('getCheckoutCompletedAt')->willReturn(null);

        $mapped = $this->mapper->map($order, isInRealTime: true);

        $this->assertTrue($mapped->isInRealTime);
    }

    public function testSkipsItemWithNoVariant(): void
    {
        $item = $this->createMock(OrderItemInterface::class);
        $item->method('getVariant')->willReturn(null);

        $customer = $this->createMock(CustomerInterface::class);
        $customer->method('getId')->willReturn(1);
        $customer->method('getEmail')->willReturn('x@example.com');
        $customer->method('getFirstName')->willReturn('');
        $customer->method('getLastName')->willReturn('');
        $customer->method('isSubscribedToNewsletter')->willReturn(false);

        $order = $this->createMock(OrderInterface::class);
        $order->method('getId')->willReturn(1);
        $order->method('getCustomer')->willReturn($customer);
        $order->method('getBillingAddress')->willReturn(null);
        $order->method('getShippingAddress')->willReturn(null);
        $order->method('getCurrencyCode')->willReturn('EUR');
        $order->method('getTotal')->willReturn(0);
        $order->method('getTaxTotal')->willReturn(0);
        $order->method('getShippingTotal')->willReturn(0);
        $order->method('getAdjustmentsTotalRecursively')->willReturn(0);
        $order->method('getItems')->willReturn(new ArrayCollection([$item]));
        $order->method('getCheckoutCompletedAt')->willReturn(null);

        $mapped = $this->mapper->map($order);

        $this->assertCount(0, $mapped->lines);
    }
}
