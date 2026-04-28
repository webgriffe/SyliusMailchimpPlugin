<?php

declare(strict_types=1);

namespace Webgriffe\SyliusMailchimpPlugin\Resolver;

use Sylius\Component\Core\Model\CustomerInterface;

final class FnameLnameMergeFieldsProvider implements MergeFieldsProviderInterface
{
    #[\Override]
    public function provide(CustomerInterface $customer): array
    {
        return [
            'FNAME' => (string) $customer->getFirstName(),
            'LNAME' => (string) $customer->getLastName(),
        ];
    }
}
