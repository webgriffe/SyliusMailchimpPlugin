<?php

declare(strict_types=1);

namespace Tests\Webgriffe\SyliusMailchimpPlugin\Unit\Resolver;

use PHPUnit\Framework\TestCase;
use Tests\Webgriffe\SyliusMailchimpPlugin\Entity\Customer\Customer;
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
        $customer = new Customer();
        $customer->setFirstName('John');
        $customer->setLastName('Doe');

        $fields = $this->provider->provide($customer);

        $this->assertSame('John', $fields['FNAME']);
        $this->assertSame('Doe', $fields['LNAME']);
    }

    public function test_provides_empty_strings_when_names_are_null(): void
    {
        $customer = new Customer();

        $fields = $this->provider->provide($customer);

        $this->assertSame('', $fields['FNAME']);
        $this->assertSame('', $fields['LNAME']);
    }
}
