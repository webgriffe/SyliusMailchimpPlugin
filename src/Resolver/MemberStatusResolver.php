<?php

declare(strict_types=1);

namespace Webgriffe\SyliusMailchimpPlugin\Resolver;

use Sylius\Component\Core\Model\CustomerInterface;

final class MemberStatusResolver implements MemberStatusResolverInterface
{
    public function __construct(
        private readonly string $defaultStatus,
    ) {
    }

    #[\Override]
    public function resolve(CustomerInterface $customer): string
    {
        return $this->defaultStatus;
    }
}
