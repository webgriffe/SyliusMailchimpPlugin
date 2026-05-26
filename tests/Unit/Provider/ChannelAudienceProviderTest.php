<?php

declare(strict_types=1);

namespace Tests\Webgriffe\SyliusMailchimpPlugin\Unit\Provider;

use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\ChannelInterface;
use Tests\Webgriffe\SyliusMailchimpPlugin\Entity\Channel\Channel;
use Webgriffe\SyliusMailchimpPlugin\Exception\AudienceNotFoundException;
use Webgriffe\SyliusMailchimpPlugin\Provider\ChannelAudienceProvider;
use Webgriffe\SyliusMailchimpPlugin\ValueObject\Audience;

final class ChannelAudienceProviderTest extends TestCase
{
    private ChannelAudienceProvider $provider;

    protected function setUp(): void
    {
        $this->provider = new ChannelAudienceProvider();
    }

    public function test_throws_exception_when_channel_does_not_implement_mailchimp_aware(): void
    {
        $this->expectException(AudienceNotFoundException::class);

        $channel = $this->createMock(ChannelInterface::class);
        $channel->method('getCode')->willReturn('WEB');

        $this->provider->getAudience($channel);
    }

    public function test_throws_exception_when_audience_id_is_null(): void
    {
        $this->expectException(AudienceNotFoundException::class);

        $channel = new Channel();
        $channel->setMailchimpAudienceId(null);

        $this->provider->getAudience($channel);
    }

    public function test_throws_exception_when_audience_id_is_empty_string(): void
    {
        $this->expectException(AudienceNotFoundException::class);

        $channel = new Channel();
        $channel->setMailchimpAudienceId('');

        $this->provider->getAudience($channel);
    }

    public function test_returns_audience_when_configured(): void
    {
        $channel = new Channel();
        $channel->setMailchimpAudienceId('abc123');

        $audience = $this->provider->getAudience($channel);

        $this->assertInstanceOf(Audience::class, $audience);
        $this->assertSame('abc123', $audience->id);
        $this->assertSame($channel, $audience->channel);
    }

    public function test_locale_code_is_accepted_but_ignored(): void
    {
        $channel = new Channel();
        $channel->setMailchimpAudienceId('abc123');

        $audience = $this->provider->getAudience($channel, 'it_IT');

        $this->assertSame('abc123', $audience->id);
    }
}
