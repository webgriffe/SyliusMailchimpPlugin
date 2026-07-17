<?php

declare(strict_types=1);

namespace Tests\Webgriffe\SyliusMailchimpPlugin\Unit\Enqueuer;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Tests\Webgriffe\SyliusMailchimpPlugin\Entity\Channel\Channel;
use Tests\Webgriffe\SyliusMailchimpPlugin\Unit\ReflectionIdTrait;
use Webgriffe\SyliusMailchimpPlugin\Enqueuer\StoreEnqueuer;
use Webgriffe\SyliusMailchimpPlugin\Message\Store\StoreCreate;
use Webgriffe\SyliusMailchimpPlugin\Provider\AudienceProviderInterface;
use Webgriffe\SyliusMailchimpPlugin\Resolver\StoreIdentifierResolverInterface;
use Webgriffe\SyliusMailchimpPlugin\ValueObject\Audience;

final class StoreEnqueuerTest extends TestCase
{
    use ReflectionIdTrait;

    private MessageBusInterface $messageBus;

    private MockObject&AudienceProviderInterface $audienceProvider;

    private MockObject&StoreIdentifierResolverInterface $storeIdentifierResolver;

    private StoreEnqueuer $enqueuer;

    protected function setUp(): void
    {
        $this->messageBus = $this->createMock(MessageBusInterface::class);
        $this->audienceProvider = $this->createMock(AudienceProviderInterface::class);
        $this->storeIdentifierResolver = $this->createMock(StoreIdentifierResolverInterface::class);
        $this->enqueuer = new StoreEnqueuer(
            $this->messageBus,
            $this->audienceProvider,
            $this->storeIdentifierResolver,
            new NullLogger(),
        );
    }

    public function test_enqueue_dispatches_store_create(): void
    {
        $channel = $this->createChannel(id: 1, code: 'WEB');
        $audience = new Audience('aud123', $channel);
        $this->audienceProvider->method('getAudience')->willReturn($audience);
        $this->storeIdentifierResolver->method('resolve')->willReturn('WEB-aud123');

        $this->messageBus->expects($this->once())
            ->method('dispatch')
            ->with($this->isInstanceOf(StoreCreate::class))
            ->willReturnCallback(fn (object $msg) => new Envelope($msg));

        $this->enqueuer->enqueue($channel);
    }

    public function test_enqueue_skips_when_channel_has_no_int_id(): void
    {
        $channel = new Channel();
        $channel->setCode('WEB');
        $channel->setName('Test Store');
        $channel->setHostname('https://example.com');
        $channel->setContactEmail('test@example.com');

        $this->messageBus->expects($this->never())->method('dispatch');

        $this->enqueuer->enqueue($channel);
    }

    public function test_enqueue_does_not_throw_when_dispatch_fails(): void
    {
        $channel = $this->createChannel(id: 1, code: 'WEB');
        $this->audienceProvider->method('getAudience')->willReturn(new Audience('aud123', $channel));
        $this->storeIdentifierResolver->method('resolve')->willReturn('WEB-aud123');
        $this->messageBus->method('dispatch')->willThrowException(new \RuntimeException('Handler failed'));

        $this->enqueuer->enqueue($channel);

        $this->expectNotToPerformAssertions();
    }

    private function createChannel(int $id, string $code): Channel
    {
        $channel = new Channel();
        self::setIdOnObject($channel, $id);
        $channel->setCode($code);
        $channel->setName('Test Store');
        $channel->setHostname('https://example.com');
        $channel->setContactEmail('test@example.com');
        $channel->setMailchimpAudienceId('aud123');

        return $channel;
    }
}
