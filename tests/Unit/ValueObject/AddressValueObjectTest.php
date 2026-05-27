<?php

declare(strict_types=1);

namespace Tests\Webgriffe\SyliusMailchimpPlugin\Unit\ValueObject;

use PHPUnit\Framework\TestCase;
use Webgriffe\SyliusMailchimpPlugin\ValueObject\Address;

final class AddressValueObjectTest extends TestCase
{
    public function test_address_stores_fields(): void
    {
        $address = new Address('John Doe', 'Via Roma 1', '', 'Milano', 'MI', 'MI', '20100', 'Italy', 'IT');

        $this->assertSame('John Doe', $address->name);
        $this->assertSame('IT', $address->countryCode);
    }
}
