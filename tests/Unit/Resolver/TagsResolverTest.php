<?php

declare(strict_types=1);

namespace Tests\Webgriffe\SyliusMailchimpPlugin\Unit\Resolver;

use PHPUnit\Framework\TestCase;
use Tests\Webgriffe\SyliusMailchimpPlugin\Entity\Customer\Customer;
use Webgriffe\SyliusMailchimpPlugin\Resolver\TagsResolver;

final class TagsResolverTest extends TestCase
{
    public function test_returns_empty_array_by_default(): void
    {
        $resolver = new TagsResolver();
        $customer = new Customer();

        $this->assertSame([], $resolver->resolve($customer));
    }
}
