<?php

declare(strict_types=1);

namespace Tests\Webgriffe\SyliusMailchimpPlugin\Unit\Resolver;

use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\CustomerInterface;
use Webgriffe\SyliusMailchimpPlugin\Resolver\MemberStatusResolver;

final class MemberStatusResolverTest extends TestCase
{
    public function test_returns_subscribed_when_configured_as_subscribed(): void
    {
        $resolver = new MemberStatusResolver('subscribed');
        $customer = $this->createMock(CustomerInterface::class);

        $this->assertSame('subscribed', $resolver->resolve($customer));
    }

    public function test_returns_pending_when_configured_as_pending(): void
    {
        $resolver = new MemberStatusResolver('pending');
        $customer = $this->createMock(CustomerInterface::class);

        $this->assertSame('pending', $resolver->resolve($customer));
    }
}
