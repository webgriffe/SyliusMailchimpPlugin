<?php

declare(strict_types=1);

namespace Webgriffe\SyliusMailchimpPlugin\Mapper;

use Sylius\Component\Core\Model\CustomerInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Webgriffe\SyliusMailchimpPlugin\ValueObject\Address;
use Webgriffe\SyliusMailchimpPlugin\ValueObject\EcommerceCustomer;

final class EcommerceCustomerMapper
{
    public function mapFromOrder(OrderInterface $order): EcommerceCustomer
    {
        $customer = $order->getCustomer();
        $billingAddress = $order->getBillingAddress();

        $customerId = $customer instanceof CustomerInterface ? (string) $customer->getId() : (string) $order->getId();
        $emailAddress = $customer instanceof CustomerInterface ? (string) $customer->getEmail() : '';

        $address = null;
        if ($billingAddress !== null) {
            $address = new Address(
                name: trim(sprintf('%s %s', $billingAddress->getFirstName() ?? '', $billingAddress->getLastName() ?? '')),
                address1: (string) $billingAddress->getStreet(),
                city: (string) $billingAddress->getCity(),
                postalCode: (string) $billingAddress->getPostcode(),
                country: (string) $billingAddress->getCountryCode(),
                countryCode: (string) $billingAddress->getCountryCode(),
                province: (string) $billingAddress->getProvinceName(),
                provinceCode: (string) $billingAddress->getProvinceCode(),
            );
        }

        return new EcommerceCustomer(
            id: $customerId,
            emailAddress: $emailAddress,
            firstName: $customer instanceof CustomerInterface ? (string) $customer->getFirstName() : '',
            lastName: $customer instanceof CustomerInterface ? (string) $customer->getLastName() : '',
            address: $address,
            optInStatus: $customer instanceof CustomerInterface && $customer->isSubscribedToNewsletter(),
        );
    }
}
