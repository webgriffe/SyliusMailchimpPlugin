<?php

declare(strict_types=1);

namespace Tests\Webgriffe\SyliusMailchimpPlugin\Unit\EventSubscriber;

use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Symfony\Component\EventDispatcher\GenericEvent;
use Tests\Webgriffe\SyliusMailchimpPlugin\Entity\Channel\Channel;
use Webgriffe\SyliusMailchimpPlugin\Enqueuer\ProductEnqueuerInterface;
use Webgriffe\SyliusMailchimpPlugin\EventSubscriber\ProductSubscriber;

final class ProductSubscriberTest extends TestCase
{
    private MockObject&ProductEnqueuerInterface $productEnqueuer;

    private ProductSubscriber $subscriber;

    protected function setUp(): void
    {
        $this->productEnqueuer = $this->createMock(ProductEnqueuerInterface::class);
        $this->subscriber = new ProductSubscriber($this->productEnqueuer, new NullLogger());
    }

    public function testGetSubscribedEvents(): void
    {
        $events = ProductSubscriber::getSubscribedEvents();

        self::assertArrayHasKey('sylius.product.post_create', $events);
        self::assertArrayHasKey('sylius.product.post_update', $events);
        self::assertArrayHasKey('sylius.product.pre_delete', $events);
    }

    public function testOnProductPostCreateEnqueuesAsNew(): void
    {
        $product = $this->createMock(ProductInterface::class);

        $this->productEnqueuer->expects(self::once())->method('enqueue')->with($product, isNew: true);

        $this->subscriber->onProductPostCreate(new GenericEvent($product));
    }

    public function testOnProductPostUpdateEnqueuesAsNotNew(): void
    {
        $product = $this->createMock(ProductInterface::class);

        $this->productEnqueuer->expects(self::once())->method('enqueue')->with($product, isNew: false);

        $this->subscriber->onProductPostUpdate(new GenericEvent($product));
    }

    public function testOnProductPostCreateIgnoresNonProductSubject(): void
    {
        $this->productEnqueuer->expects(self::never())->method('enqueue');

        $this->subscriber->onProductPostCreate(new GenericEvent(new \stdClass()));
    }

    public function testOnProductPostUpdateIgnoresNonProductSubject(): void
    {
        $this->productEnqueuer->expects(self::never())->method('enqueue');

        $this->subscriber->onProductPostUpdate(new GenericEvent(new \stdClass()));
    }

    public function testOnProductPreDeleteEnqueuesRemovalForEachMailchimpChannel(): void
    {
        $channel1 = new Channel();
        $channel1->setCode('CHANNEL_1');
        $channel2 = new Channel();
        $channel2->setCode('CHANNEL_2');

        $product = $this->createMock(ProductInterface::class);
        $product->method('getChannels')->willReturn(new ArrayCollection([$channel1, $channel2]));

        $this->productEnqueuer->expects(self::exactly(2))->method('buildProductId')->with($product)->willReturn('prod-123');
        $this->productEnqueuer->expects(self::exactly(2))->method('enqueueRemoval');

        $this->subscriber->onProductPreDelete(new GenericEvent($product));
    }

    public function testOnProductPreDeleteSkipsNonMailchimpChannels(): void
    {
        $channel = $this->createMock(ChannelInterface::class);

        $product = $this->createMock(ProductInterface::class);
        $product->method('getChannels')->willReturn(new ArrayCollection([$channel]));

        $this->productEnqueuer->expects(self::never())->method('enqueueRemoval');

        $this->subscriber->onProductPreDelete(new GenericEvent($product));
    }

    public function testOnProductPreDeleteIgnoresNonProductSubject(): void
    {
        $this->productEnqueuer->expects(self::never())->method('enqueueRemoval');

        $this->subscriber->onProductPreDelete(new GenericEvent(new \stdClass()));
    }
}
