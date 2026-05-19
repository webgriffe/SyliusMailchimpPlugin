<?php

declare(strict_types=1);

namespace Tests\Webgriffe\SyliusMailchimpPlugin\Unit\Resolver;

use PHPUnit\Framework\TestCase;
use Tests\Webgriffe\SyliusMailchimpPlugin\Entity\Customer\Customer;
use Webgriffe\SyliusMailchimpPlugin\Resolver\MemberStatusResolver;

final class MemberStatusResolverTest extends TestCase
{
    public function test_returns_subscribed_when_configured_as_subscribed(): void
    {
        $resolver = new MemberStatusResolver('subscribed');
        $customer = new Customer();

        $this->assertSame('subscribed', $resolver->resolve($customer));
    }

    public function test_returns_pending_when_configured_as_pending(): void
    {
        $resolver = new MemberStatusResolver('pending');
        $customer = new Customer();

        $this->assertSame('pending', $resolver->resolve($customer));
    }
}
