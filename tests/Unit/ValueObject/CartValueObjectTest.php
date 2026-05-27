<?php

declare(strict_types=1);

namespace Tests\Webgriffe\SyliusMailchimpPlugin\Unit\ValueObject;

use PHPUnit\Framework\TestCase;
use Webgriffe\SyliusMailchimpPlugin\ValueObject\Cart;
use Webgriffe\SyliusMailchimpPlugin\ValueObject\CartLine;
use Webgriffe\SyliusMailchimpPlugin\ValueObject\EcommerceCustomer;

final class CartValueObjectTest extends TestCase
{
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
}
