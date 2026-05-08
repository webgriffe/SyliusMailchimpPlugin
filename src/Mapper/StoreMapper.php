<?php

declare(strict_types=1);

namespace Webgriffe\SyliusMailchimpPlugin\Mapper;

use Sylius\Component\Core\Model\ChannelInterface;
use Webgriffe\SyliusMailchimpPlugin\Model\ChannelMailchimpAwareInterface;
use Webgriffe\SyliusMailchimpPlugin\Util\IdSanitizer;
use Webgriffe\SyliusMailchimpPlugin\ValueObject\Store;

final class StoreMapper
{
    public function map(ChannelInterface&ChannelMailchimpAwareInterface $channel): Store
    {
        $storeId = IdSanitizer::sanitize((string) $channel->getCode());
        $locales = $channel->getLocales();
        $firstLocale = $locales->first();
        $primaryLocale = $firstLocale !== false ? (string) $firstLocale->getCode() : 'en';
        $currencies = $channel->getCurrencies();
        $firstCurrency = $currencies->first();
        $currencyCode = $firstCurrency !== false ? (string) $firstCurrency->getCode() : 'USD';

        return new Store(
            id: $storeId,
            name: (string) $channel->getName(),
            domain: rtrim((string) $channel->getHostname(), '/'),
            emailAddress: (string) $channel->getContactEmail(),
            currencyCode: $currencyCode,
            primaryLocale: $primaryLocale,
            listId: (string) $channel->getMailchimpAudienceId(),
        );
    }
}
