<?php

declare(strict_types=1);

namespace Webgriffe\SyliusMailchimpPlugin\Resolver;

use Sylius\Component\Core\Model\CustomerInterface;

final class ContactInfoMergeFieldsProvider implements MergeFieldsProviderInterface
{
    #[\Override]
    public function provide(CustomerInterface $customer): array
    {
        $fields = [];

        $phoneNumber = $customer->getPhoneNumber();
        if ($phoneNumber !== null && $phoneNumber !== '') {
            $fields['PHONE'] = $phoneNumber;
        }

        return $fields;
    }
}
