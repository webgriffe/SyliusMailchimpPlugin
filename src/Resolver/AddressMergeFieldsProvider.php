<?php

declare(strict_types=1);

namespace Webgriffe\SyliusMailchimpPlugin\Resolver;

use Sylius\Component\Core\Model\AddressInterface;
use Sylius\Component\Core\Model\CustomerInterface;

final class AddressMergeFieldsProvider implements MergeFieldsProviderInterface
{
    #[\Override]
    public function provide(CustomerInterface $customer): array
    {
        $address = $this->resolveAddress($customer);
        if ($address === null) {
            return [];
        }

        return [
            'ADDRESS' => [
                'addr1' => (string) $address->getStreet(),
                'addr2' => '',
                'city' => (string) $address->getCity(),
                'state' => (string) ($address->getProvinceCode() ?? $address->getProvinceName()),
                'zip' => (string) $address->getPostcode(),
                'country' => (string) $address->getCountryCode(),
            ],
        ];
    }

    private function resolveAddress(CustomerInterface $customer): ?AddressInterface
    {
        $addresses = $customer->getAddresses();

        if (count($addresses) === 0) {
            return null;
        }

        if (count($addresses) === 1) {
            /** @var AddressInterface $address */
            $address = $addresses->first();

            return $address;
        }

        $default = $customer->getDefaultAddress();
        if ($default !== null) {
            return $default;
        }

        /** @var AddressInterface $address */
        $address = $addresses->first();

        return $address;
    }
}
