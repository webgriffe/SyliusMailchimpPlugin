<?php

declare(strict_types=1);

namespace Tests\Webgriffe\SyliusMailchimpPlugin\Unit\Mapper;

use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\CustomerInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\OrderItemInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Model\ProductVariantInterface;
use Webgriffe\SyliusMailchimpPlugin\Mapper\CartMapper;
use Webgriffe\SyliusMailchimpPlugin\Mapper\EcommerceCustomerMapper;
use Webgriffe\SyliusMailchimpPlugin\Model\ChannelMailchimpAwareInterface;

final class CartMapperTest extends TestCase
{
    private CartMapper $mapper;

    protected function setUp(): void
    {
        $this->mapper = new CartMapper(new EcommerceCustomerMapper());
    }

    public function testMapsOrderToCart(): void
    {
        $customer = $this->createMock(CustomerInterface::class);
        $customer->method('getId')->willReturn(1);
        $customer->method('getEmail')->willReturn('john@example.com');
        $customer->method('getFirstName')->willReturn('John');
        $customer->method('getLastName')->willReturn('Doe');
        $customer->method('isSubscribedToNewsletter')->willReturn(false);

        $product = $this->createMock(ProductInterface::class);
        $product->method('getCode')->willReturn('TSHIRT');

        $variant = $this->createMock(ProductVariantInterface::class);
        $variant->method('getCode')->willReturn('TSHIRT-L');
        $variant->method('getProduct')->willReturn($product);

        $item = $this->createMock(OrderItemInterface::class);
        $item->method('getId')->willReturn(10);
        $item->method('getVariant')->willReturn($variant);
        $item->method('getQuantity')->willReturn(2);
        $item->method('getUnitPrice')->willReturn(1999);

        $channel = $this->createChannelMock('https://example.com');

        $order = $this->createMock(OrderInterface::class);
        $order->method('getTokenValue')->willReturn('abc123token');
        $order->method('getCustomer')->willReturn($customer);
        $order->method('getBillingAddress')->willReturn(null);
        $order->method('getCurrencyCode')->willReturn('EUR');
        $order->method('getTotal')->willReturn(3998);
        $order->method('getItems')->willReturn(new ArrayCollection([$item]));

        $cart = $this->mapper->map($order, $channel);

        $this->assertSame('abc123token', $cart->id);
        $this->assertSame('john@example.com', $cart->customer->emailAddress);
        $this->assertSame('https://example.com/checkout', $cart->checkoutUrl);
        $this->assertSame('EUR', $cart->currencyCode);
        $this->assertSame(39.98, $cart->orderTotal);
        $this->assertCount(1, $cart->lines);
        $this->assertSame('10_TSHIRT-L', $cart->lines[0]->id);
        $this->assertSame('TSHIRT', $cart->lines[0]->productId);
        $this->assertSame('TSHIRT_TSHIRT-L', $cart->lines[0]->productVariantId);
        $this->assertSame(2, $cart->lines[0]->quantity);
        $this->assertSame(19.99, $cart->lines[0]->price);
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

        $channel = $this->createChannelMock('https://example.com');

        $order = $this->createMock(OrderInterface::class);
        $order->method('getTokenValue')->willReturn('tok');
        $order->method('getCustomer')->willReturn($customer);
        $order->method('getBillingAddress')->willReturn(null);
        $order->method('getCurrencyCode')->willReturn('EUR');
        $order->method('getTotal')->willReturn(0);
        $order->method('getItems')->willReturn(new ArrayCollection([$item]));

        $cart = $this->mapper->map($order, $channel);

        $this->assertCount(0, $cart->lines);
    }

    /** @return ChannelInterface&ChannelMailchimpAwareInterface */
    private function createChannelMock(string $hostname): ChannelInterface&ChannelMailchimpAwareInterface
    {
        /** @var ChannelInterface&ChannelMailchimpAwareInterface $channel */
        $channel = $this->createMockForIntersectionOfInterfaces([ChannelInterface::class, ChannelMailchimpAwareInterface::class]);
        $channel->method('getHostname')->willReturn($hostname);

        return $channel;
    }
}
