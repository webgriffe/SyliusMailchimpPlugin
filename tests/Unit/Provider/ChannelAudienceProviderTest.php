<?php

declare(strict_types=1);

namespace Tests\Webgriffe\SyliusMailchimpPlugin\Unit\Provider;

use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\ChannelInterface;
use Webgriffe\SyliusMailchimpPlugin\Exception\AudienceNotFoundException;
use Webgriffe\SyliusMailchimpPlugin\Model\ChannelMailchimpAwareInterface;
use Webgriffe\SyliusMailchimpPlugin\Provider\ChannelAudienceProvider;

interface TestMailchimpChannelInterface extends ChannelInterface, ChannelMailchimpAwareInterface
{
}

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

        $this->provider->getAudienceId($channel);
    }

    public function test_throws_exception_when_audience_id_is_null(): void
    {
        $this->expectException(AudienceNotFoundException::class);

        $channel = $this->createMailchimpChannelMock(null);
        $this->provider->getAudienceId($channel);
    }

    public function test_throws_exception_when_audience_id_is_empty_string(): void
    {
        $this->expectException(AudienceNotFoundException::class);

        $channel = $this->createMailchimpChannelMock('');
        $this->provider->getAudienceId($channel);
    }

    public function test_returns_audience_id_when_configured(): void
    {
        $channel = $this->createMailchimpChannelMock('abc123');

        $result = $this->provider->getAudienceId($channel);

        $this->assertSame('abc123', $result);
    }

    private function createMailchimpChannelMock(?string $audienceId): TestMailchimpChannelInterface
    {
        $channel = $this->createMock(TestMailchimpChannelInterface::class);
        $channel->method('getCode')->willReturn('WEB');
        $channel->method('getMailchimpAudienceId')->willReturn($audienceId);

        return $channel;
    }
}
