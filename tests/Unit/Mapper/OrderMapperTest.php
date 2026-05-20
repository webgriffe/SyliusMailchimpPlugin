<?php

declare(strict_types=1);

namespace Tests\Webgriffe\SyliusMailchimpPlugin\Unit\Mapper;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\Address;
use Sylius\Component\Core\Model\AdjustmentInterface;
use Sylius\Component\Core\Model\OrderItem;
use Sylius\Component\Core\Model\Product;
use Sylius\Component\Core\Model\ProductVariant;
use Sylius\Component\Order\Model\Adjustment;
use Tests\Webgriffe\SyliusMailchimpPlugin\Entity\Customer\Customer;
use Tests\Webgriffe\SyliusMailchimpPlugin\Entity\Order\Order;
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
        $customer = new Customer();
        self::setId($customer, 1);
        $customer->setEmail('john@example.com');
        $customer->setFirstName('John');
        $customer->setLastName('Doe');
        $customer->setSubscribedToNewsletter(true);

        $product = new Product();
        $product->setCode('TSHIRT');

        $variant = new ProductVariant();
        $variant->setCode('TSHIRT-L');
        $product->addVariant($variant);

        $item = new OrderItem();
        self::setId($item, 5);
        $item->setVariant($variant);
        $item->setUnitPrice(2999);
        self::setQuantity($item, 1);

        $itemPromoAdj = new Adjustment();
        $itemPromoAdj->setType(AdjustmentInterface::ORDER_PROMOTION_ADJUSTMENT);
        $itemPromoAdj->setAmount(-500);
        $item->addAdjustment($itemPromoAdj);

        $billingAddress = new Address();
        $billingAddress->setFirstName('John');
        $billingAddress->setLastName('Doe');
        $billingAddress->setStreet('123 Main St');
        $billingAddress->setCity('New York');
        $billingAddress->setPostcode('10001');
        $billingAddress->setCountryCode('US');
        $billingAddress->setProvinceName('New York');
        $billingAddress->setProvinceCode('NY');

        $processedAt = new DateTimeImmutable('2024-01-15 10:00:00');

        $order = new Order();
        self::setId($order, 42);
        $order->setCustomer($customer);
        $order->setBillingAddress($billingAddress);
        $order->setCurrencyCode('USD');
        $order->setCheckoutCompletedAt($processedAt);

        $taxAdj = new Adjustment();
        $taxAdj->setType(AdjustmentInterface::TAX_ADJUSTMENT);
        $taxAdj->setAmount(300);
        $order->addAdjustment($taxAdj);

        $shippingAdj = new Adjustment();
        $shippingAdj->setType(AdjustmentInterface::SHIPPING_ADJUSTMENT);
        $shippingAdj->setAmount(500);
        $order->addAdjustment($shippingAdj);

        $orderPromoAdj = new Adjustment();
        $orderPromoAdj->setType(AdjustmentInterface::ORDER_PROMOTION_ADJUSTMENT);
        $orderPromoAdj->setAmount(300);
        $order->addAdjustment($orderPromoAdj);

        $order->addItem($item);
        self::setTotal($order, 2999);

        $mapped = $this->mapper->map($order);

        $this->assertSame('42', $mapped->id);
        $this->assertSame('john@example.com', $mapped->customer->emailAddress);
        $this->assertSame('USD', $mapped->currencyCode);
        $this->assertSame(29.99, $mapped->orderTotal);
        $this->assertSame(3.0, $mapped->taxTotal);
        $this->assertSame(8.0, $mapped->shippingTotal);
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
        $customer = new Customer();
        self::setId($customer, 1);
        $customer->setEmail('x@example.com');

        $order = new Order();
        self::setId($order, 1);
        $order->setCustomer($customer);
        $order->setCurrencyCode('EUR');

        $mapped = $this->mapper->map($order, isInRealTime: true);

        $this->assertTrue($mapped->isInRealTime);
    }

    public function testSkipsItemWithNoVariant(): void
    {
        $item = new OrderItem();

        $customer = new Customer();
        self::setId($customer, 1);
        $customer->setEmail('x@example.com');

        $order = new Order();
        self::setId($order, 1);
        $order->setCustomer($customer);
        $order->setCurrencyCode('EUR');
        $order->addItem($item);

        $mapped = $this->mapper->map($order);

        $this->assertCount(0, $mapped->lines);
    }

    private static function setId(object $entity, int $id): void
    {
        $ref = new \ReflectionProperty($entity, 'id');
        $ref->setValue($entity, $id);
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
