<?php

declare(strict_types=1);

namespace Tests\Webgriffe\SyliusMailchimpPlugin\Unit\Enqueuer;

use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Locale\Model\LocaleInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Webgriffe\SyliusMailchimpPlugin\Enqueuer\ProductEnqueuer;
use Webgriffe\SyliusMailchimpPlugin\Message\Product\ProductCreate;
use Webgriffe\SyliusMailchimpPlugin\Message\Product\ProductRemove;
use Webgriffe\SyliusMailchimpPlugin\Message\Product\ProductUpdate;
use Webgriffe\SyliusMailchimpPlugin\Model\ChannelMailchimpAwareInterface;

final class ProductEnqueuerTest extends TestCase
{
    private MessageBusInterface $messageBus;

    private ProductEnqueuer $enqueuer;

    protected function setUp(): void
    {
        $this->messageBus = $this->createMock(MessageBusInterface::class);
        $this->enqueuer = new ProductEnqueuer($this->messageBus, new NullLogger());
    }

    public function testEnqueueNewProductDispatchesProductCreate(): void
    {
        $channel = $this->createChannelMock(id: 1);
        $product = $this->createProductMock(id: 10, channels: [$channel]);

        $this->messageBus->expects($this->once())
            ->method('dispatch')
            ->with($this->isInstanceOf(ProductCreate::class))
            ->willReturnCallback(fn (object $msg) => new Envelope($msg));

        $this->enqueuer->enqueue($product, isNew: true);
    }

    public function testEnqueueExistingProductDispatchesProductUpdate(): void
    {
        $channel = $this->createChannelMock(id: 1);
        $product = $this->createProductMock(id: 10, channels: [$channel]);

        $this->messageBus->expects($this->once())
            ->method('dispatch')
            ->with($this->isInstanceOf(ProductUpdate::class))
            ->willReturnCallback(fn (object $msg) => new Envelope($msg));

        $this->enqueuer->enqueue($product, isNew: false);
    }

    public function testEnqueueDispatchesForEachMailchimpChannel(): void
    {
        $channel1 = $this->createChannelMock(id: 1);
        $channel2 = $this->createChannelMock(id: 2);
        $product = $this->createProductMock(id: 5, channels: [$channel1, $channel2]);

        $this->messageBus->expects($this->exactly(2))
            ->method('dispatch')
            ->willReturnCallback(fn (object $msg) => new Envelope($msg));

        $this->enqueuer->enqueue($product);
    }

    public function testEnqueueRemovalDispatchesProductRemove(): void
    {
        $this->messageBus->expects($this->once())
            ->method('dispatch')
            ->with($this->equalTo(new ProductRemove('WEB', 'TSHIRT')))
            ->willReturnCallback(fn (object $msg) => new Envelope($msg));

        $this->enqueuer->enqueueRemoval('WEB', 'TSHIRT');
    }

    private function createProductMock(int $id, array $channels = []): ProductInterface
    {
        $product = $this->createMock(ProductInterface::class);
        $product->method('getId')->willReturn($id);
        $product->method('getCode')->willReturn('PROD-' . $id);
        $product->method('getChannels')->willReturn(new ArrayCollection($channels));

        return $product;
    }

    /** @return ChannelInterface&ChannelMailchimpAwareInterface */
    private function createChannelMock(int $id): ChannelInterface&ChannelMailchimpAwareInterface
    {
        $locale = $this->createMock(LocaleInterface::class);
        $locale->method('getCode')->willReturn('en_US');

        /** @var ChannelInterface&ChannelMailchimpAwareInterface $channel */
        $channel = $this->createMockForIntersectionOfInterfaces([ChannelInterface::class, ChannelMailchimpAwareInterface::class]);
        $channel->method('getId')->willReturn($id);
        $channel->method('getDefaultLocale')->willReturn($locale);

        return $channel;
    }
}
