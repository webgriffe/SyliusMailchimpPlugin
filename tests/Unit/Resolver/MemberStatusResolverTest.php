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
        $customer->setSubscribedToNewsletter(true);

        $this->assertSame('subscribed', $resolver->resolve($customer));
    }

    public function test_returns_pending_when_configured_as_pending(): void
    {
        $resolver = new MemberStatusResolver('pending');
        $customer = new Customer();
        $customer->setSubscribedToNewsletter(true);

        $this->assertSame('pending', $resolver->resolve($customer));
    }

    public function test_returns_unsubscribed_when_customer_is_not_subscribed_to_newsletter(): void
    {
        $resolver = new MemberStatusResolver('subscribed');
        $customer = new Customer();
        $customer->setSubscribedToNewsletter(false);

        $this->assertSame('unsubscribed', $resolver->resolve($customer));
    }
}
