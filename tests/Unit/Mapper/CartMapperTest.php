<?php

declare(strict_types=1);

namespace Tests\Webgriffe\SyliusMailchimpPlugin\Unit\Mapper;

use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\OrderItem;
use Sylius\Component\Core\Model\Product;
use Sylius\Component\Core\Model\ProductVariant;
use Tests\Webgriffe\SyliusMailchimpPlugin\Entity\Channel\Channel;
use Tests\Webgriffe\SyliusMailchimpPlugin\Entity\Customer\Customer;
use Tests\Webgriffe\SyliusMailchimpPlugin\Entity\Order\Order;
use Tests\Webgriffe\SyliusMailchimpPlugin\Unit\ReflectionIdTrait;
use Webgriffe\SyliusMailchimpPlugin\Mapper\CartMapper;
use Webgriffe\SyliusMailchimpPlugin\Mapper\EcommerceCustomerMapper;

final class CartMapperTest extends TestCase
{
    use ReflectionIdTrait;

    private CartMapper $mapper;

    protected function setUp(): void
    {
        $this->mapper = new CartMapper(new EcommerceCustomerMapper());
    }

    public function test_maps_order_to_cart(): void
    {
        $customer = new Customer();
        self::setIdOnObject($customer, 1);
        $customer->setEmail('john@example.com');
        $customer->setFirstName('John');
        $customer->setLastName('Doe');

        $product = new Product();
        $product->setCode('TSHIRT');

        $variant = new ProductVariant();
        $variant->setCode('TSHIRT-L');
        $product->addVariant($variant);

        $item = new OrderItem();
        self::setIdOnObject($item, 10);
        $item->setVariant($variant);
        $item->setUnitPrice(1999);
        self::setQuantity($item, 2);

        $channel = new Channel();
        $channel->setHostname('https://example.com');

        $order = new Order();
        self::setIdOnObject($order, 42);
        $order->setCustomer($customer);
        $order->setCurrencyCode('EUR');
        $order->addItem($item);
        self::setTotal($order, 3998);

        $cart = $this->mapper->map($order, $channel);

        $this->assertSame('42', $cart->id);
        $this->assertSame('john@example.com', $cart->customer->emailAddress);
        $this->assertSame('https://example.com/checkout', $cart->checkoutUrl);
        $this->assertSame('EUR', $cart->currencyCode);
        $this->assertSame(39.98, $cart->orderTotal);
        $this->assertCount(1, $cart->lines);
        $this->assertSame('10_TSHIRT-L', $cart->lines[0]->id);
        $this->assertSame('TSHIRT', $cart->lines[0]->productId);
        $this->assertSame('TSHIRT-L', $cart->lines[0]->productVariantId);
        $this->assertSame(2, $cart->lines[0]->quantity);
        $this->assertSame(19.99, $cart->lines[0]->price);
    }

    public function test_skips_item_with_no_variant(): void
    {
        $item = new OrderItem();

        $customer = new Customer();
        self::setIdOnObject($customer, 1);
        $customer->setEmail('x@example.com');

        $channel = new Channel();
        $channel->setHostname('https://example.com');

        $order = new Order();
        self::setIdOnObject($order, 99);
        $order->setCustomer($customer);
        $order->setCurrencyCode('EUR');
        $order->addItem($item);

        $cart = $this->mapper->map($order, $channel);

        $this->assertCount(0, $cart->lines);
    }

    private static function setQuantity(OrderItem $item, int $quantity): void
    {
        $ref = new \ReflectionProperty($item, 'quantity');
        $ref->setValue($item, $quantity);
    }

    private static function setTotal(Order $order, int $total): void
    {
        $ref = new \ReflectionProperty($order, 'total');
        $ref->setValue($order, $total);
    }
}
