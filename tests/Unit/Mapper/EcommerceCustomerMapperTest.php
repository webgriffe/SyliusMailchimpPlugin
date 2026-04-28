<?php

declare(strict_types=1);

namespace Tests\Webgriffe\SyliusMailchimpPlugin\Unit\Mapper;

use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\AddressInterface;
use Sylius\Component\Core\Model\CustomerInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Webgriffe\SyliusMailchimpPlugin\Mapper\EcommerceCustomerMapper;

final class EcommerceCustomerMapperTest extends TestCase
{
    private EcommerceCustomerMapper $mapper;

    protected function setUp(): void
    {
        $this->mapper = new EcommerceCustomerMapper();
    }

    public function testMapsOrderWithCustomerAndBillingAddress(): void
    {
        $address = $this->createMock(AddressInterface::class);
        $address->method('getFirstName')->willReturn('John');
        $address->method('getLastName')->willReturn('Doe');
        $address->method('getStreet')->willReturn('123 Main St');
        $address->method('getCity')->willReturn('New York');
        $address->method('getPostcode')->willReturn('10001');
        $address->method('getCountryCode')->willReturn('US');
        $address->method('getProvinceName')->willReturn('New York');
        $address->method('getProvinceCode')->willReturn('NY');

        $customer = $this->createMock(CustomerInterface::class);
        $customer->method('getId')->willReturn(42);
        $customer->method('getEmail')->willReturn('john@example.com');
        $customer->method('getFirstName')->willReturn('John');
        $customer->method('getLastName')->willReturn('Doe');
        $customer->method('isSubscribedToNewsletter')->willReturn(true);

        $order = $this->createMock(OrderInterface::class);
        $order->method('getCustomer')->willReturn($customer);
        $order->method('getBillingAddress')->willReturn($address);

        $ecommerceCustomer = $this->mapper->mapFromOrder($order);

        $this->assertSame('42', $ecommerceCustomer->id);
        $this->assertSame('john@example.com', $ecommerceCustomer->emailAddress);
        $this->assertSame('John', $ecommerceCustomer->firstName);
        $this->assertSame('Doe', $ecommerceCustomer->lastName);
        $this->assertTrue($ecommerceCustomer->optInStatus);
        $this->assertNotNull($ecommerceCustomer->address);
        $this->assertSame('John Doe', $ecommerceCustomer->address->name);
        $this->assertSame('123 Main St', $ecommerceCustomer->address->address1);
        $this->assertSame('US', $ecommerceCustomer->address->countryCode);
    }

    public function testMapsOrderWithoutCustomer(): void
    {
        $order = $this->createMock(OrderInterface::class);
        $order->method('getCustomer')->willReturn(null);
        $order->method('getBillingAddress')->willReturn(null);
        $order->method('getId')->willReturn(99);

        $ecommerceCustomer = $this->mapper->mapFromOrder($order);

        $this->assertSame('99', $ecommerceCustomer->id);
        $this->assertSame('', $ecommerceCustomer->emailAddress);
        $this->assertSame('', $ecommerceCustomer->firstName);
        $this->assertSame('', $ecommerceCustomer->lastName);
        $this->assertFalse($ecommerceCustomer->optInStatus);
        $this->assertNull($ecommerceCustomer->address);
    }

    public function testMapsOrderWithNullAddressNameFields(): void
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

        $customer = $this->createMock(CustomerInterface::class);
        $customer->method('getId')->willReturn(1);
        $customer->method('getEmail')->willReturn('test@example.com');
        $customer->method('getFirstName')->willReturn(null);
        $customer->method('getLastName')->willReturn(null);
        $customer->method('isSubscribedToNewsletter')->willReturn(false);

        $order = $this->createMock(OrderInterface::class);
        $order->method('getCustomer')->willReturn($customer);
        $order->method('getBillingAddress')->willReturn($address);

        $ecommerceCustomer = $this->mapper->mapFromOrder($order);

        $this->assertNotNull($ecommerceCustomer->address);
        $this->assertSame('', $ecommerceCustomer->address->name);
    }
}
