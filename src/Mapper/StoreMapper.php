<?php

declare(strict_types=1);

namespace Webgriffe\SyliusMailchimpPlugin\Mapper;

use DateTimeZone;
use Sylius\Component\Core\Model\ShopBillingDataInterface;
use Webgriffe\SyliusMailchimpPlugin\Resolver\StoreIdentifierResolverInterface;
use Webgriffe\SyliusMailchimpPlugin\ValueObject\Audience;

final class StoreMapper implements StoreMapperInterface
{
    public function __construct(
        private readonly StoreIdentifierResolverInterface $storeIdentifierResolver,
    ) {
    }

    #[\Override]
    public function map(Audience $audience): array
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

        $payload = [
            'id' => $this->storeIdentifierResolver->resolve($audience),
            'name' => (string) $channel->getName(),
            'domain' => rtrim((string) $channel->getHostname(), '/'),
            'email_address' => (string) $channel->getContactEmail(),
            'currency_code' => $currencyCode,
            'primary_locale' => $primaryLocale,
            'timezone' => $timezone,
            'list_id' => $audience->id,
            'platform' => 'Sylius',
        ];

        if ($address !== '') {
            $payload['address'] = ['address1' => $address];
        }

        return $payload;
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
