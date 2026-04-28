<?php

declare(strict_types=1);

namespace Tests\Webgriffe\SyliusMailchimpPlugin\Unit\Mapper;

use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Currency\Model\CurrencyInterface;
use Sylius\Component\Locale\Model\LocaleInterface;
use Webgriffe\SyliusMailchimpPlugin\Mapper\StoreMapper;
use Webgriffe\SyliusMailchimpPlugin\Model\ChannelMailchimpAwareInterface;

final class StoreMapperTest extends TestCase
{
    private StoreMapper $mapper;

    protected function setUp(): void
    {
        $this->mapper = new StoreMapper();
    }

    public function testMapsChannelToStore(): void
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
            locales: new ArrayCollection([$locale]),
            currencies: new ArrayCollection([$currency]),
        );

        $store = $this->mapper->map($channel);

        $this->assertSame('WEB', $store->id);
        $this->assertSame('Web Store', $store->name);
        $this->assertSame('https://example.com', $store->domain);
        $this->assertSame('store@example.com', $store->emailAddress);
        $this->assertSame('USD', $store->currencyCode);
        $this->assertSame('en_US', $store->primaryLocale);
    }

    public function testStripsTrailingSlashFromDomain(): void
    {
        $channel = $this->createChannelMock(hostname: 'https://example.com/');

        $store = $this->mapper->map($channel);

        $this->assertSame('https://example.com', $store->domain);
    }

    public function testDefaultsToEnLocaleWhenNoLocales(): void
    {
        $channel = $this->createChannelMock(locales: new ArrayCollection([]));

        $store = $this->mapper->map($channel);

        $this->assertSame('en', $store->primaryLocale);
    }

    public function testDefaultsToUsdWhenNoCurrencies(): void
    {
        $channel = $this->createChannelMock(currencies: new ArrayCollection([]));

        $store = $this->mapper->map($channel);

        $this->assertSame('USD', $store->currencyCode);
    }

    public function testSanitizesChannelCodeForStoreId(): void
    {
        $channel = $this->createChannelMock(code: 'My Channel#1');

        $store = $this->mapper->map($channel);

        $this->assertSame('My-Channel-1', $store->id);
    }

    /** @return ChannelInterface&ChannelMailchimpAwareInterface */
    private function createChannelMock(
        string $code = 'WEB',
        string $name = 'Web Store',
        string $hostname = 'https://example.com',
        string $contactEmail = 'store@example.com',
        ?ArrayCollection $locales = null,
        ?ArrayCollection $currencies = null,
    ): ChannelInterface&ChannelMailchimpAwareInterface {
        /** @var ChannelInterface&ChannelMailchimpAwareInterface $channel */
        $channel = $this->createMockForIntersectionOfInterfaces([ChannelInterface::class, ChannelMailchimpAwareInterface::class]);
        $channel->method('getCode')->willReturn($code);
        $channel->method('getName')->willReturn($name);
        $channel->method('getHostname')->willReturn($hostname);
        $channel->method('getContactEmail')->willReturn($contactEmail);
        $channel->method('getLocales')->willReturn($locales ?? new ArrayCollection([]));
        $channel->method('getCurrencies')->willReturn($currencies ?? new ArrayCollection([]));

        return $channel;
    }
}
