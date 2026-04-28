<?php

declare(strict_types=1);

namespace Webgriffe\SyliusMailchimpPlugin\Resolver;

use Sylius\Component\Core\Model\CustomerInterface;

final class TagsResolver implements TagsResolverInterface
{
    #[\Override]
    public function resolve(CustomerInterface $customer): array
    {
        return [];
    }
}
