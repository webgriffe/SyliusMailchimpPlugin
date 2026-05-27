<?php

declare(strict_types=1);

namespace Tests\Webgriffe\SyliusMailchimpPlugin\Unit\ValueObject;

use PHPUnit\Framework\TestCase;
use Webgriffe\SyliusMailchimpPlugin\ValueObject\EcommerceCustomer;
use Webgriffe\SyliusMailchimpPlugin\ValueObject\Order;
use Webgriffe\SyliusMailchimpPlugin\ValueObject\OrderLine;

final class OrderValueObjectTest extends TestCase
{
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
}
