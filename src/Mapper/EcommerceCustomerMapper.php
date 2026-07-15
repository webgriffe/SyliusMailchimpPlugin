<?php

declare(strict_types=1);

namespace Webgriffe\SyliusMailchimpPlugin\Mapper;

use Sylius\Component\Core\Model\CustomerInterface;
use Sylius\Component\Core\Model\OrderInterface;

final class EcommerceCustomerMapper implements EcommerceCustomerMapperInterface
{
    #[\Override]
    public function mapFromOrder(OrderInterface $order): array
    {
        $customer = $order->getCustomer();
        $billingAddress = $order->getBillingAddress();

        $customerId = $customer instanceof CustomerInterface ? (string) $customer->getId() : (string) $order->getId();
        $emailAddress = $customer instanceof CustomerInterface ? (string) $customer->getEmail() : '';

        $payload = [
            'id' => $customerId,
            'email_address' => $emailAddress,
            'first_name' => $customer instanceof CustomerInterface ? (string) $customer->getFirstName() : '',
            'last_name' => $customer instanceof CustomerInterface ? (string) $customer->getLastName() : '',
            'opt_in_status' => $customer instanceof CustomerInterface && $customer->isSubscribedToNewsletter(),
        ];

        if ($billingAddress !== null) {
            $payload['address'] = [
                'name' => trim(sprintf('%s %s', $billingAddress->getFirstName() ?? '', $billingAddress->getLastName() ?? '')),
                'address1' => (string) $billingAddress->getStreet(),
                'city' => (string) $billingAddress->getCity(),
                'postal_code' => (string) $billingAddress->getPostcode(),
                'country' => (string) $billingAddress->getCountryCode(),
                'country_code' => (string) $billingAddress->getCountryCode(),
                'province' => (string) $billingAddress->getProvinceName(),
                'province_code' => (string) $billingAddress->getProvinceCode(),
            ];
        }

        return $payload;
    }
}
