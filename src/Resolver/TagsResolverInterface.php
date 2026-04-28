<?php

declare(strict_types=1);

namespace Webgriffe\SyliusMailchimpPlugin\Resolver;

use Sylius\Component\Core\Model\CustomerInterface;

interface TagsResolverInterface
{
    /** @return string[] */
    public function resolve(CustomerInterface $customer): array;
}
