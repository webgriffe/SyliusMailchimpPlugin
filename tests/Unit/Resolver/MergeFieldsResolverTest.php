<?php

declare(strict_types=1);

namespace Tests\Webgriffe\SyliusMailchimpPlugin\Unit\Resolver;

use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\CustomerInterface;
use Webgriffe\SyliusMailchimpPlugin\Resolver\FnameLnameMergeFieldsProvider;
use Webgriffe\SyliusMailchimpPlugin\Resolver\MergeFieldsProviderInterface;
use Webgriffe\SyliusMailchimpPlugin\Resolver\MergeFieldsResolver;

final class MergeFieldsResolverTest extends TestCase
{
    public function test_returns_empty_merge_fields_with_no_providers(): void
    {
        $resolver = new MergeFieldsResolver([]);
        $customer = $this->createMock(CustomerInterface::class);

        $result = $resolver->resolve($customer);

        $this->assertSame('', $result->firstName);
        $this->assertSame('', $result->lastName);
        $this->assertSame([], $result->extra);
    }

    public function test_uses_fname_lname_provider(): void
    {
        $customer = $this->createMock(CustomerInterface::class);
        $customer->method('getFirstName')->willReturn('Jane');
        $customer->method('getLastName')->willReturn('Smith');

        $resolver = new MergeFieldsResolver([new FnameLnameMergeFieldsProvider()]);
        $result = $resolver->resolve($customer);

        $this->assertSame('Jane', $result->firstName);
        $this->assertSame('Smith', $result->lastName);
    }

    public function test_merges_extra_fields_from_multiple_providers(): void
    {
        $customer = $this->createMock(CustomerInterface::class);
        $customer->method('getFirstName')->willReturn('Jane');
        $customer->method('getLastName')->willReturn('Smith');

        $extraProvider = $this->createMock(MergeFieldsProviderInterface::class);
        $extraProvider->method('provide')->willReturn(['PHONE' => '555-1234', 'CITY' => 'Rome']);

        $resolver = new MergeFieldsResolver([new FnameLnameMergeFieldsProvider(), $extraProvider]);
        $result = $resolver->resolve($customer);

        $this->assertSame('Jane', $result->firstName);
        $this->assertSame('Smith', $result->lastName);
        $this->assertSame('555-1234', $result->extra['PHONE']);
        $this->assertSame('Rome', $result->extra['CITY']);
    }

    public function test_later_provider_overrides_fname_lname(): void
    {
        $customer = $this->createMock(CustomerInterface::class);
        $customer->method('getFirstName')->willReturn('Jane');
        $customer->method('getLastName')->willReturn('Smith');

        $overrideProvider = $this->createMock(MergeFieldsProviderInterface::class);
        $overrideProvider->method('provide')->willReturn(['FNAME' => 'Override', 'LNAME' => 'Name']);

        $resolver = new MergeFieldsResolver([new FnameLnameMergeFieldsProvider(), $overrideProvider]);
        $result = $resolver->resolve($customer);

        $this->assertSame('Override', $result->firstName);
        $this->assertSame('Name', $result->lastName);
    }
}
