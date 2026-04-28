<?php

declare(strict_types=1);

namespace Tests\Webgriffe\SyliusMailchimpPlugin\Unit\ValueObject;

use PHPUnit\Framework\TestCase;
use Webgriffe\SyliusMailchimpPlugin\ValueObject\Address;
use Webgriffe\SyliusMailchimpPlugin\ValueObject\Cart;
use Webgriffe\SyliusMailchimpPlugin\ValueObject\CartLine;
use Webgriffe\SyliusMailchimpPlugin\ValueObject\EcommerceCustomer;
use Webgriffe\SyliusMailchimpPlugin\ValueObject\Order;
use Webgriffe\SyliusMailchimpPlugin\ValueObject\OrderLine;
use Webgriffe\SyliusMailchimpPlugin\ValueObject\Product;
use Webgriffe\SyliusMailchimpPlugin\ValueObject\ProductVariant;
use Webgriffe\SyliusMailchimpPlugin\ValueObject\Store;

final class EcommerceValueObjectsTest extends TestCase
{
    public function test_store_stores_all_fields(): void
    {
        $store = new Store('store-1', 'My Shop', 'myshop.com', 'admin@myshop.com', 'EUR', 'it_IT');

        $this->assertSame('store-1', $store->id);
        $this->assertSame('My Shop', $store->name);
        $this->assertSame('EUR', $store->currencyCode);
        $this->assertSame('it_IT', $store->primaryLocale);
    }

    public function test_product_stores_variants(): void
    {
        $variant = new ProductVariant('var-1', 'Red', 'https://example.com/red', 'SKU-001', 19.99);
        $product = new Product('prod-1', 'T-Shirt', 'https://example.com/tshirt', [$variant]);

        $this->assertSame('prod-1', $product->id);
        $this->assertCount(1, $product->variants);
        $this->assertSame('var-1', $product->variants[0]->id);
        $this->assertSame(19.99, $product->variants[0]->price);
    }

    public function test_address_stores_fields(): void
    {
        $address = new Address('John Doe', 'Via Roma 1', '', 'Milano', 'MI', 'MI', '20100', 'Italy', 'IT');

        $this->assertSame('John Doe', $address->name);
        $this->assertSame('IT', $address->countryCode);
    }

    public function test_cart_stores_customer_and_lines(): void
    {
        $customer = new EcommerceCustomer('cust-1', 'user@example.com', 'John', 'Doe');
        $line = new CartLine('line-1', 'prod-1', 'var-1', 2, 19.99);
        $cart = new Cart('cart-1', $customer, 'https://example.com/checkout', 'EUR', 39.98, [$line]);

        $this->assertSame('cart-1', $cart->id);
        $this->assertSame('user@example.com', $cart->customer->emailAddress);
        $this->assertCount(1, $cart->lines);
        $this->assertSame(2, $cart->lines[0]->quantity);
    }

    public function test_order_stores_customer_and_lines(): void
    {
        $customer = new EcommerceCustomer('cust-1', 'user@example.com');
        $line = new OrderLine('line-1', 'prod-1', 'var-1', 1, 59.99, 5.0);
        $order = new Order(
            'order-1',
            $customer,
            'EUR',
            54.99,
            [$line],
            taxTotal: 5.0,
            isInRealTime: true,
        );

        $this->assertSame('order-1', $order->id);
        $this->assertTrue($order->isInRealTime);
        $this->assertSame(5.0, $order->taxTotal);
        $this->assertCount(1, $order->lines);
        $this->assertSame(5.0, $order->lines[0]->discount);
    }

    public function test_ecommerce_customer_with_address(): void
    {
        $address = new Address('John', 'Via Roma', '', 'Rome', '', '', '00100', 'Italy', 'IT');
        $customer = new EcommerceCustomer('c-1', 'john@example.com', 'John', 'Doe', $address, true);

        $this->assertTrue($customer->optInStatus);
        $this->assertNotNull($customer->address);
        $this->assertSame('IT', $customer->address->countryCode);
    }
}
