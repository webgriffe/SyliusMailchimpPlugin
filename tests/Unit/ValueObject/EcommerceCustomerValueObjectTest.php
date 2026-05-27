<?php

declare(strict_types=1);

namespace Tests\Webgriffe\SyliusMailchimpPlugin\Unit\ValueObject;

use PHPUnit\Framework\TestCase;
use Webgriffe\SyliusMailchimpPlugin\ValueObject\Address;
use Webgriffe\SyliusMailchimpPlugin\ValueObject\EcommerceCustomer;

final class EcommerceCustomerValueObjectTest extends TestCase
{
    public function test_ecommerce_customer_with_address(): void
    {
        $address = new Address('John', 'Via Roma', '', 'Rome', '', '', '00100', 'Italy', 'IT');
        $customer = new EcommerceCustomer('c-1', 'john@example.com', 'John', 'Doe', $address, true);

        $this->assertTrue($customer->optInStatus);
        $this->assertNotNull($customer->address);
        $this->assertSame('IT', $customer->address->countryCode);
    }
}
