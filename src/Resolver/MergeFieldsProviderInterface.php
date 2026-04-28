<?php

declare(strict_types=1);

namespace Webgriffe\SyliusMailchimpPlugin\Resolver;

use Sylius\Component\Core\Model\CustomerInterface;

interface MergeFieldsProviderInterface
{
    /**
     * @return array<string, string>
     */
    public function provide(CustomerInterface $customer): array;
}
