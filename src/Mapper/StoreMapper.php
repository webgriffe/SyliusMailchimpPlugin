<?php

declare(strict_types=1);

namespace Webgriffe\SyliusMailchimpPlugin\Mapper;

use DateTimeZone;
use Sylius\Component\Core\Model\ShopBillingDataInterface;
use Webgriffe\SyliusMailchimpPlugin\Resolver\StoreIdentifierResolverInterface;
use Webgriffe\SyliusMailchimpPlugin\ValueObject\Audience;
use Webgriffe\SyliusMailchimpPlugin\ValueObject\Store;

final class StoreMapper implements StoreMapperInterface
{
    public function __construct(
        private readonly StoreIdentifierResolverInterface $storeIdentifierResolver,
    ) {
    }

    #[\Override]
    public function map(Audience $audience): Store
    {
        $channel = $audience->channel;

        $defaultLocale = $channel->getDefaultLocale();
        $primaryLocale = $defaultLocale !== null
            ? mb_substr((string) $defaultLocale->getCode(), 0, 2)
            : 'en';

        $baseCurrency = $channel->getBaseCurrency();
        $currencyCode = $baseCurrency !== null ? (string) $baseCurrency->getCode() : 'USD';

        $timezone = '';
        $address = '';
        $shopBillingData = $channel->getShopBillingData();
        if ($shopBillingData instanceof ShopBillingDataInterface) {
            $timezone = self::resolveTimezone($shopBillingData->getCountryCode());
            $address = implode(', ', array_filter([
                $shopBillingData->getStreet(),
                $shopBillingData->getCity(),
                $shopBillingData->getPostcode(),
                $shopBillingData->getCountryCode(),
            ]));
        }

        return new Store(
            id: $this->storeIdentifierResolver->resolve($audience),
            name: (string) $channel->getName(),
            domain: rtrim((string) $channel->getHostname(), '/'),
            emailAddress: (string) $channel->getContactEmail(),
            currencyCode: $currencyCode,
            primaryLocale: $primaryLocale,
            listId: $audience->id,
            timezone: $timezone,
            address: $address,
        );
    }

    private static function resolveTimezone(?string $countryCode): string
    {
        if ($countryCode === null || $countryCode === '') {
            return '';
        }

        $identifiers = DateTimeZone::listIdentifiers(DateTimeZone::PER_COUNTRY, $countryCode);

        return count($identifiers) > 0 ? $identifiers[0] : '';
    }
}
