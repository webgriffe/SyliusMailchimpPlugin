<?php

declare(strict_types=1);

namespace Tests\Webgriffe\SyliusMailchimpPlugin\Unit\MessageHandler\Store;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Sylius\Component\Channel\Repository\ChannelRepositoryInterface;
use Sylius\Component\Core\Model\Channel;
use Tests\Webgriffe\SyliusMailchimpPlugin\Entity\Channel\Channel as MailchimpChannel;
use Webgriffe\SyliusMailchimpPlugin\Client\MailchimpClientInterface;
use Webgriffe\SyliusMailchimpPlugin\Message\Store\StoreCreate;
use Webgriffe\SyliusMailchimpPlugin\MessageHandler\Store\StoreCreateHandler;
use Webgriffe\SyliusMailchimpPlugin\Provider\AudienceProviderInterface;
use Webgriffe\SyliusMailchimpPlugin\ValueObject\Audience;

final class StoreCreateHandlerTest extends TestCase
{
    private MockObject&ChannelRepositoryInterface $channelRepository;

    private MockObject&AudienceProviderInterface $audienceProvider;

    private MockObject&MailchimpClientInterface $mailchimpClient;

    protected function setUp(): void
    {
        $this->channelRepository = $this->createMock(ChannelRepositoryInterface::class);
        $this->audienceProvider = $this->createMock(AudienceProviderInterface::class);
        $this->mailchimpClient = $this->createMock(MailchimpClientInterface::class);
    }

    public function test_skips_when_channel_not_found(): void
    {
        $this->channelRepository->method('find')->with(1)->willReturn(null);
        $this->mailchimpClient->expects(self::never())->method('upsertStore');

        $handler = $this->buildHandler();
        $handler(new StoreCreate(1));
    }

    public function test_skips_when_channel_is_not_mailchimp_aware(): void
    {
        $this->channelRepository->method('find')->with(1)->willReturn(new Channel());
        $this->mailchimpClient->expects(self::never())->method('upsertStore');

        $handler = $this->buildHandler();
        $handler(new StoreCreate(1));
    }

    public function test_upserts_store_with_audience_from_channel(): void
    {
        $channel = new MailchimpChannel();
        $audience = new Audience('list123', $channel);

        $this->channelRepository->method('find')->with(42)->willReturn($channel);
        $this->audienceProvider->method('getAudience')->with($channel)->willReturn($audience);
        $this->mailchimpClient->expects(self::once())->method('upsertStore')->with($audience);

        $handler = $this->buildHandler();
        $handler(new StoreCreate(42));
    }

    private function buildHandler(): StoreCreateHandler
    {
        return new StoreCreateHandler(
            $this->channelRepository,
            $this->audienceProvider,
            $this->mailchimpClient,
            new NullLogger(),
        );
    }
}
