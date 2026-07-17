<?php

declare(strict_types=1);

namespace Tests\Webgriffe\SyliusMailchimpPlugin\Unit\Enqueuer;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Tests\Webgriffe\SyliusMailchimpPlugin\Entity\Channel\Channel;
use Tests\Webgriffe\SyliusMailchimpPlugin\Entity\Order\Order;
use Tests\Webgriffe\SyliusMailchimpPlugin\Unit\ReflectionIdTrait;
use Webgriffe\SyliusMailchimpPlugin\Enqueuer\CartEnqueuer;
use Webgriffe\SyliusMailchimpPlugin\Message\Cart\CartCreate;
use Webgriffe\SyliusMailchimpPlugin\Message\Cart\CartRemove;
use Webgriffe\SyliusMailchimpPlugin\Message\Cart\CartUpdate;
use Webgriffe\SyliusMailchimpPlugin\Provider\AudienceProviderInterface;
use Webgriffe\SyliusMailchimpPlugin\Resolver\StoreIdentifierResolverInterface;
use Webgriffe\SyliusMailchimpPlugin\ValueObject\Audience;

final class CartEnqueuerTest extends TestCase
{
    use ReflectionIdTrait;

    private MessageBusInterface $messageBus;

    private MockObject&AudienceProviderInterface $audienceProvider;

    private MockObject&StoreIdentifierResolverInterface $storeIdentifierResolver;

    private CartEnqueuer $enqueuer;

    protected function setUp(): void
    {
        $this->messageBus = $this->createMock(MessageBusInterface::class);
        $this->audienceProvider = $this->createMock(AudienceProviderInterface::class);
        $this->storeIdentifierResolver = $this->createMock(StoreIdentifierResolverInterface::class);
        $this->enqueuer = new CartEnqueuer(
            $this->messageBus,
            new NullLogger(),
            $this->audienceProvider,
            $this->storeIdentifierResolver,
        );
    }

    public function test_enqueue_dispatches_cart_create_when_no_mailchimp_cart_id(): void
    {
        $channel = $this->createChannel(id: 1, code: 'WEB');
        $order = $this->createOrder(id: 5, channel: $channel, mailchimpCartId: null);

        $this->messageBus->expects($this->once())
            ->method('dispatch')
            ->with($this->isInstanceOf(CartCreate::class))
            ->willReturnCallback(fn (object $msg) => new Envelope($msg));

        $this->enqueuer->enqueue($order);
    }

    public function test_enqueue_dispatches_cart_update_when_mailchimp_cart_id_exists(): void
    {
        $channel = $this->createChannel(id: 1, code: 'WEB');
        $order = $this->createOrder(id: 5, channel: $channel, mailchimpCartId: 'existing-cart-id');

        $this->messageBus->expects($this->once())
            ->method('dispatch')
            ->with($this->isInstanceOf(CartUpdate::class))
            ->willReturnCallback(fn (object $msg) => new Envelope($msg));

        $this->enqueuer->enqueue($order);
    }

    public function test_enqueue_removal_dispatches_cart_remove(): void
    {
        $channel = $this->createChannel(id: 1, code: 'WEB', audienceId: 'abc123');
        $order = $this->createOrder(id: 5, channel: $channel, mailchimpCartId: 'cart-token-abc');

        $audience = new Audience('abc123', $channel);
        $this->audienceProvider->method('getAudience')->willReturn($audience);
        $this->storeIdentifierResolver->method('resolve')->willReturn('WEB-abc123');

        $this->messageBus->expects($this->once())
            ->method('dispatch')
            ->with($this->equalTo(new CartRemove('WEB-abc123', 'cart-token-abc')))
            ->willReturnCallback(fn (object $msg) => new Envelope($msg));

        $this->enqueuer->enqueueRemoval($order);
    }

    public function test_enqueue_removal_skips_when_no_cart_id(): void
    {
        $channel = $this->createChannel(id: 1, code: 'WEB', audienceId: 'abc123');
        $order = $this->createOrder(id: 5, channel: $channel, mailchimpCartId: null);

        $this->messageBus->expects($this->never())->method('dispatch');

        $this->enqueuer->enqueueRemoval($order);
    }

    public function test_enqueue_does_not_throw_when_dispatch_fails(): void
    {
        $channel = $this->createChannel(id: 1, code: 'WEB');
        $order = $this->createOrder(id: 5, channel: $channel, mailchimpCartId: null);

        $this->messageBus->method('dispatch')->willThrowException(new \RuntimeException('Handler failed'));

        $this->enqueuer->enqueue($order);

        $this->expectNotToPerformAssertions();
    }

    public function test_enqueue_removal_does_not_throw_when_dispatch_fails(): void
    {
        $channel = $this->createChannel(id: 1, code: 'WEB', audienceId: 'abc123');
        $order = $this->createOrder(id: 5, channel: $channel, mailchimpCartId: 'cart-token-abc');

        $this->audienceProvider->method('getAudience')->willReturn(new Audience('abc123', $channel));
        $this->storeIdentifierResolver->method('resolve')->willReturn('WEB-abc123');
        $this->messageBus->method('dispatch')->willThrowException(new \RuntimeException('Handler failed'));

        $this->enqueuer->enqueueRemoval($order);

        $this->expectNotToPerformAssertions();
    }

    private function createOrder(int $id, Channel $channel, ?string $mailchimpCartId): Order
    {
        $order = new Order();
        self::setIdOnObject($order, $id);
        $order->setChannel($channel);
        if ($mailchimpCartId !== null) {
            $order->setMailchimpCartId($mailchimpCartId);
        }

        return $order;
    }

    private function createChannel(int $id, string $code, string $audienceId = 'audience123'): Channel
    {
        $channel = new Channel();
        self::setIdOnObject($channel, $id);
        $channel->setCode($code);
        $channel->setMailchimpAudienceId($audienceId);

        return $channel;
    }
}
