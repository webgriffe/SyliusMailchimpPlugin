<?php

declare(strict_types=1);

namespace Tests\Webgriffe\SyliusMailchimpPlugin\Unit\Resolver;

use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\Address;
use Tests\Webgriffe\SyliusMailchimpPlugin\Entity\Customer\Customer;
use Webgriffe\SyliusMailchimpPlugin\Resolver\AddressMergeFieldsProvider;

final class AddressMergeFieldsProviderTest extends TestCase
{
    private AddressMergeFieldsProvider $provider;

    protected function setUp(): void
    {
        $this->provider = new AddressMergeFieldsProvider();
    }

    public function test_omits_address_when_customer_has_none(): void
    {
        $customer = new Customer();

        $fields = $this->provider->provide($customer);

        $this->assertArrayNotHasKey('ADDRESS', $fields);
    }

    public function test_provides_the_only_address_even_if_not_marked_as_default(): void
    {
        $customer = new Customer();
        $address = $this->createAddress('Via Roma 1', 'Bologna', 'IT', '40123');
        $customer->addAddress($address);

        $fields = $this->provider->provide($customer);

        $this->assertSame([
            'addr1' => 'Via Roma 1',
            'addr2' => '',
            'city' => 'Bologna',
            'state' => '',
            'zip' => '40123',
            'country' => 'IT',
        ], $fields['ADDRESS']);
    }

    public function test_prioritizes_default_address_when_multiple_are_present(): void
    {
        $customer = new Customer();
        $first = $this->createAddress('Via Milano 2', 'Milano', 'IT', '20100');
        $default = $this->createAddress('Via Napoli 3', 'Napoli', 'IT', '80100');
        $customer->addAddress($first);
        $customer->addAddress($default);
        $customer->setDefaultAddress($default);

        $fields = $this->provider->provide($customer);

        $this->assertSame('Via Napoli 3', $fields['ADDRESS']['addr1']);
        $this->assertSame('Napoli', $fields['ADDRESS']['city']);
    }

    public function test_falls_back_to_first_address_when_no_default_is_set(): void
    {
        $customer = new Customer();
        $first = $this->createAddress('Via Milano 2', 'Milano', 'IT', '20100');
        $second = $this->createAddress('Via Napoli 3', 'Napoli', 'IT', '80100');
        $customer->addAddress($first);
        $customer->addAddress($second);

        $fields = $this->provider->provide($customer);

        $this->assertSame('Via Milano 2', $fields['ADDRESS']['addr1']);
    }

    private function createAddress(string $street, string $city, string $countryCode, string $postcode): Address
    {
        $address = new Address();
        $address->setStreet($street);
        $address->setCity($city);
        $address->setCountryCode($countryCode);
        $address->setPostcode($postcode);

        return $address;
    }
}
