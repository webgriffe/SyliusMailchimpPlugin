<?php

declare(strict_types=1);

namespace Tests\Webgriffe\SyliusMailchimpPlugin\Unit\Mapper;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\ShopBillingDataInterface;
use Sylius\Component\Currency\Model\Currency;
use Sylius\Component\Locale\Model\Locale;
use Tests\Webgriffe\SyliusMailchimpPlugin\Entity\Channel\Channel;
use Webgriffe\SyliusMailchimpPlugin\Mapper\StoreMapper;
use Webgriffe\SyliusMailchimpPlugin\Resolver\StoreIdentifierResolverInterface;
use Webgriffe\SyliusMailchimpPlugin\ValueObject\Audience;

final class StoreMapperTest extends TestCase
{
    private MockObject&StoreIdentifierResolverInterface $resolver;

    private StoreMapper $mapper;

    protected function setUp(): void
    {
        $this->resolver = $this->createMock(StoreIdentifierResolverInterface::class);
        $this->resolver->method('resolve')->willReturnCallback(
            static fn (Audience $a) => sprintf('%s-%s', (string) $a->channel->getCode(), $a->id),
        );
        $this->mapper = new StoreMapper($this->resolver);
    }

    public function test_maps_audience_to_store(): void
    {
        $locale = new Locale();
        $locale->setCode('en_US');

        $currency = new Currency();
        $currency->setCode('USD');

        $channel = $this->createChannel(
            code: 'WEB',
            name: 'Web Store',
            hostname: 'https://example.com',
            contactEmail: 'store@example.com',
            defaultLocale: $locale,
            baseCurrency: $currency,
        );

        $audience = new Audience('abc123', $channel);
        $store = $this->mapper->map($audience);

        $this->assertSame('WEB-abc123', $store['id']);
        $this->assertSame('abc123', $store['list_id']);
        $this->assertSame('Web Store', $store['name']);
        $this->assertSame('https://example.com', $store['domain']);
        $this->assertSame('store@example.com', $store['email_address']);
        $this->assertSame('USD', $store['currency_code']);
        $this->assertSame('en', $store['primary_locale']);
        $this->assertSame('Sylius', $store['platform']);
    }

    public function test_strips_trailing_slash_from_domain(): void
    {
        $channel = $this->createChannel(hostname: 'https://example.com/');
        $store = $this->mapper->map(new Audience('aud', $channel));

        $this->assertSame('https://example.com', $store['domain']);
    }

    public function test_defaults_to_en_locale_when_no_default_locale(): void
    {
        $channel = $this->createChannel(defaultLocale: null);
        $store = $this->mapper->map(new Audience('aud', $channel));

        $this->assertSame('en', $store['primary_locale']);
    }

    public function test_defaults_to_usd_when_no_base_currency(): void
    {
        $channel = $this->createChannel(baseCurrency: null);
        $store = $this->mapper->map(new Audience('aud', $channel));

        $this->assertSame('USD', $store['currency_code']);
    }

    public function test_truncates_locale_to_two_chars(): void
    {
        $locale = new Locale();
        $locale->setCode('it_IT');

        $channel = $this->createChannel(defaultLocale: $locale);
        $store = $this->mapper->map(new Audience('aud', $channel));

        $this->assertSame('it', $store['primary_locale']);
    }

    public function test_maps_address_and_timezone_from_shop_billing_data(): void
    {
        $billing = $this->createMock(ShopBillingDataInterface::class);
        $billing->method('getStreet')->willReturn('Via Roma 1');
        $billing->method('getCity')->willReturn('Milan');
        $billing->method('getPostcode')->willReturn('20121');
        $billing->method('getCountryCode')->willReturn('IT');

        $channel = $this->createChannel(shopBillingData: $billing);
        $store = $this->mapper->map(new Audience('aud', $channel));

        $this->assertSame('Via Roma 1, Milan, 20121, IT', $store['address']['address1']);
        $this->assertNotSame('', $store['timezone']);
    }

    public function test_address_and_timezone_are_empty_without_shop_billing_data(): void
    {
        $channel = $this->createChannel(shopBillingData: null);
        $store = $this->mapper->map(new Audience('aud', $channel));

        $this->assertArrayNotHasKey('address', $store);
        $this->assertSame('', $store['timezone']);
    }

    public function test_list_id_matches_audience_id(): void
    {
        $channel = $this->createChannel();
        $store = $this->mapper->map(new Audience('my-list-id', $channel));

        $this->assertSame('my-list-id', $store['list_id']);
    }

    private function createChannel(
        string $code = 'WEB',
        string $name = 'Web Store',
        string $hostname = 'https://example.com',
        string $contactEmail = 'store@example.com',
        ?Locale $defaultLocale = null,
        ?Currency $baseCurrency = null,
        ?ShopBillingDataInterface $shopBillingData = null,
    ): Channel {
        $channel = new Channel();
        $channel->setCode($code);
        $channel->setName($name);
        $channel->setHostname($hostname);
        $channel->setContactEmail($contactEmail);
        if ($defaultLocale !== null) {
            $channel->setDefaultLocale($defaultLocale);
        }
        if ($baseCurrency !== null) {
            $channel->setBaseCurrency($baseCurrency);
        }
        if ($shopBillingData !== null) {
            $channel->setShopBillingData($shopBillingData);
        }

        return $channel;
    }
}
