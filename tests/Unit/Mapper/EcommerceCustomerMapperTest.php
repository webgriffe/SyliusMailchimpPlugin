<?php

declare(strict_types=1);

namespace Tests\Webgriffe\SyliusMailchimpPlugin\Unit\Mapper;

use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\Address;
use Sylius\Component\Core\Model\AddressInterface;
use Tests\Webgriffe\SyliusMailchimpPlugin\Entity\Customer\Customer;
use Tests\Webgriffe\SyliusMailchimpPlugin\Entity\Order\Order;
use Tests\Webgriffe\SyliusMailchimpPlugin\Unit\ReflectionIdTrait;
use Webgriffe\SyliusMailchimpPlugin\Mapper\EcommerceCustomerMapper;

final class EcommerceCustomerMapperTest extends TestCase
{
    use ReflectionIdTrait;

    private EcommerceCustomerMapper $mapper;

    protected function setUp(): void
    {
        $this->mapper = new EcommerceCustomerMapper();
    }

    public function test_maps_order_with_customer_and_billing_address(): void
    {
        $address = new Address();
        $address->setFirstName('John');
        $address->setLastName('Doe');
        $address->setStreet('123 Main St');
        $address->setCity('New York');
        $address->setPostcode('10001');
        $address->setCountryCode('US');
        $address->setProvinceName('New York');
        $address->setProvinceCode('NY');

        $customer = new Customer();
        self::setIdOnObject($customer, 42);
        $customer->setEmail('john@example.com');
        $customer->setFirstName('John');
        $customer->setLastName('Doe');
        $customer->setSubscribedToNewsletter(true);

        $order = new Order();
        $order->setCustomer($customer);
        $order->setBillingAddress($address);

        $ecommerceCustomer = $this->mapper->mapFromOrder($order);

        $this->assertSame('42', $ecommerceCustomer['id']);
        $this->assertSame('john@example.com', $ecommerceCustomer['email_address']);
        $this->assertSame('John', $ecommerceCustomer['first_name']);
        $this->assertSame('Doe', $ecommerceCustomer['last_name']);
        $this->assertTrue($ecommerceCustomer['opt_in_status']);
        $this->assertArrayHasKey('address', $ecommerceCustomer);
        $this->assertSame('John Doe', $ecommerceCustomer['address']['name']);
        $this->assertSame('123 Main St', $ecommerceCustomer['address']['address1']);
        $this->assertSame('US', $ecommerceCustomer['address']['country_code']);
    }

    public function test_maps_order_without_customer(): void
    {
        $order = new Order();
        self::setIdOnObject($order, 99);

        $ecommerceCustomer = $this->mapper->mapFromOrder($order);

        $this->assertSame('99', $ecommerceCustomer['id']);
        $this->assertSame('', $ecommerceCustomer['email_address']);
        $this->assertSame('', $ecommerceCustomer['first_name']);
        $this->assertSame('', $ecommerceCustomer['last_name']);
        $this->assertFalse($ecommerceCustomer['opt_in_status']);
        $this->assertArrayNotHasKey('address', $ecommerceCustomer);
    }

    public function test_maps_order_with_null_address_name_fields(): void
    {
        $address = $this->createMock(AddressInterface::class);
        $address->method('getFirstName')->willReturn(null);
        $address->method('getLastName')->willReturn(null);
        $address->method('getStreet')->willReturn(null);
        $address->method('getCity')->willReturn(null);
        $address->method('getPostcode')->willReturn(null);
        $address->method('getCountryCode')->willReturn(null);
        $address->method('getProvinceName')->willReturn(null);
        $address->method('getProvinceCode')->willReturn(null);

        $customer = new Customer();
        self::setIdOnObject($customer, 1);
        $customer->setEmail('test@example.com');

        $order = new Order();
        $order->setCustomer($customer);
        $order->setBillingAddress($address);

        $ecommerceCustomer = $this->mapper->mapFromOrder($order);

        $this->assertArrayHasKey('address', $ecommerceCustomer);
        $this->assertSame('', $ecommerceCustomer['address']['name']);
    }
}
