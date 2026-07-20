<?php

declare(strict_types=1);

namespace Tests\Webgriffe\SyliusMailchimpPlugin\Unit\Resolver;

use PHPUnit\Framework\TestCase;
use Tests\Webgriffe\SyliusMailchimpPlugin\Entity\Customer\Customer;
use Webgriffe\SyliusMailchimpPlugin\Resolver\ContactInfoMergeFieldsProvider;

final class ContactInfoMergeFieldsProviderTest extends TestCase
{
    private ContactInfoMergeFieldsProvider $provider;

    protected function setUp(): void
    {
        $this->provider = new ContactInfoMergeFieldsProvider();
    }

    public function test_provides_phone_from_customer(): void
    {
        $customer = new Customer();
        $customer->setPhoneNumber('+39 123 456 7890');

        $fields = $this->provider->provide($customer);

        $this->assertSame('+39 123 456 7890', $fields['PHONE']);
    }

    public function test_omits_phone_when_null(): void
    {
        $customer = new Customer();

        $fields = $this->provider->provide($customer);

        $this->assertArrayNotHasKey('PHONE', $fields);
    }
}
