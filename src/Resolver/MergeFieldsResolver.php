<?php

declare(strict_types=1);

namespace Webgriffe\SyliusMailchimpPlugin\Resolver;

use Sylius\Component\Core\Model\CustomerInterface;
use Webgriffe\SyliusMailchimpPlugin\ValueObject\MergeFields;

final class MergeFieldsResolver
{
    /** @param iterable<MergeFieldsProviderInterface> $providers */
    public function __construct(
        private readonly iterable $providers = [],
    ) {
    }

    public function resolve(CustomerInterface $customer): MergeFields
    {
        $extra = [];
        $firstName = '';
        $lastName = '';

        foreach ($this->providers as $provider) {
            $fields = $provider->provide($customer);
            if (isset($fields['FNAME'])) {
                $firstName = $fields['FNAME'];
            }

            if (isset($fields['LNAME'])) {
                $lastName = $fields['LNAME'];
            }

            foreach ($fields as $key => $value) {
                if ($key !== 'FNAME' && $key !== 'LNAME') {
                    $extra[$key] = $value;
                }
            }
        }

        return new MergeFields($firstName, $lastName, $extra);
    }
}
