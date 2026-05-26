<?php

declare(strict_types=1);

namespace Tests\Webgriffe\SyliusMailchimpPlugin\Unit\Mapper;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\ShopBillingDataInterface;
use Sylius\Component\Currency\Model\CurrencyInterface;
use Sylius\Component\Locale\Model\LocaleInterface;
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

    public function testMapsAudienceToStore(): void
    {
        $locale = $this->createMock(LocaleInterface::class);
        $locale->method('getCode')->willReturn('en_US');

        $currency = $this->createMock(CurrencyInterface::class);
        $currency->method('getCode')->willReturn('USD');

        $channel = $this->createChannelMock(
            code: 'WEB',
            name: 'Web Store',
            hostname: 'https://example.com',
            contactEmail: 'store@example.com',
            defaultLocale: $locale,
            baseCurrency: $currency,
        );

        $audience = new Audience('abc123', $channel);
        $store = $this->mapper->map($audience);

        $this->assertSame('WEB-abc123', $store->id);
        $this->assertSame('abc123', $store->listId);
        $this->assertSame('Web Store', $store->name);
        $this->assertSame('https://example.com', $store->domain);
        $this->assertSame('store@example.com', $store->emailAddress);
        $this->assertSame('USD', $store->currencyCode);
        $this->assertSame('en', $store->primaryLocale);
        $this->assertSame('Sylius', $store->platform);
    }

    public function testStripsTrailingSlashFromDomain(): void
    {
        $channel = $this->createChannelMock(hostname: 'https://example.com/');
        $store = $this->mapper->map(new Audience('aud', $channel));

        $this->assertSame('https://example.com', $store->domain);
    }

    public function testDefaultsToEnLocaleWhenNoDefaultLocale(): void
    {
        $channel = $this->createChannelMock(defaultLocale: null);
        $store = $this->mapper->map(new Audience('aud', $channel));

        $this->assertSame('en', $store->primaryLocale);
    }

    public function testDefaultsToUsdWhenNoBaseCurrency(): void
    {
        $channel = $this->createChannelMock(baseCurrency: null);
        $store = $this->mapper->map(new Audience('aud', $channel));

        $this->assertSame('USD', $store->currencyCode);
    }

    public function testTruncatesLocaleToTwoChars(): void
    {
        $locale = $this->createMock(LocaleInterface::class);
        $locale->method('getCode')->willReturn('it_IT');

        $channel = $this->createChannelMock(defaultLocale: $locale);
        $store = $this->mapper->map(new Audience('aud', $channel));

        $this->assertSame('it', $store->primaryLocale);
    }

    public function testMapsAddressAndTimezoneFromShopBillingData(): void
    {
        $billing = $this->createMock(ShopBillingDataInterface::class);
        $billing->method('getStreet')->willReturn('Via Roma 1');
        $billing->method('getCity')->willReturn('Milan');
        $billing->method('getPostcode')->willReturn('20121');
        $billing->method('getCountryCode')->willReturn('IT');

        $channel = $this->createChannelMock(shopBillingData: $billing);
        $store = $this->mapper->map(new Audience('aud', $channel));

        $this->assertSame('Via Roma 1, Milan, 20121, IT', $store->address);
        $this->assertNotSame('', $store->timezone);
    }

    public function testAddressAndTimezoneAreEmptyWithoutShopBillingData(): void
    {
        $channel = $this->createChannelMock(shopBillingData: null);
        $store = $this->mapper->map(new Audience('aud', $channel));

        $this->assertSame('', $store->address);
        $this->assertSame('', $store->timezone);
    }

    public function testListIdMatchesAudienceId(): void
    {
        $channel = $this->createChannelMock();
        $store = $this->mapper->map(new Audience('my-list-id', $channel));

        $this->assertSame('my-list-id', $store->listId);
    }

    private function createChannelMock(
        string $code = 'WEB',
        string $name = 'Web Store',
        string $hostname = 'https://example.com',
        string $contactEmail = 'store@example.com',
        ?LocaleInterface $defaultLocale = null,
        ?CurrencyInterface $baseCurrency = null,
        ?ShopBillingDataInterface $shopBillingData = null,
    ): ChannelInterface {
        if ($defaultLocale === null) {
            $defaultLocale = $this->createMock(LocaleInterface::class);
            $defaultLocale->method('getCode')->willReturn('en_US');
        }

        if ($baseCurrency === null) {
            $baseCurrency = $this->createMock(CurrencyInterface::class);
            $baseCurrency->method('getCode')->willReturn('USD');
        }

        $channel = $this->createMock(ChannelInterface::class);
        $channel->method('getCode')->willReturn($code);
        $channel->method('getName')->willReturn($name);
        $channel->method('getHostname')->willReturn($hostname);
        $channel->method('getContactEmail')->willReturn($contactEmail);
        $channel->method('getDefaultLocale')->willReturn($defaultLocale);
        $channel->method('getBaseCurrency')->willReturn($baseCurrency);
        $channel->method('getShopBillingData')->willReturn($shopBillingData);

        return $channel;
    }
}
