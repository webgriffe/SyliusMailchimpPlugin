<?php

declare(strict_types=1);

namespace Tests\Webgriffe\SyliusMailchimpPlugin\Unit\Resolver;

use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\CustomerInterface;
use Webgriffe\SyliusMailchimpPlugin\Resolver\FnameLnameMergeFieldsProvider;

final class FnameLnameMergeFieldsProviderTest extends TestCase
{
    private FnameLnameMergeFieldsProvider $provider;

    protected function setUp(): void
    {
        $this->provider = new FnameLnameMergeFieldsProvider();
    }

    public function test_provides_fname_and_lname_from_customer(): void
    {
        $customer = $this->createMock(CustomerInterface::class);
        $customer->method('getFirstName')->willReturn('John');
        $customer->method('getLastName')->willReturn('Doe');

        $fields = $this->provider->provide($customer);

        $this->assertSame('John', $fields['FNAME']);
        $this->assertSame('Doe', $fields['LNAME']);
    }

    public function test_provides_empty_strings_when_names_are_null(): void
    {
        $customer = $this->createMock(CustomerInterface::class);
        $customer->method('getFirstName')->willReturn(null);
        $customer->method('getLastName')->willReturn(null);

        $fields = $this->provider->provide($customer);

        $this->assertSame('', $fields['FNAME']);
        $this->assertSame('', $fields['LNAME']);
    }
}
