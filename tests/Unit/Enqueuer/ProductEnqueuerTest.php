<?php

declare(strict_types=1);

namespace Tests\Webgriffe\SyliusMailchimpPlugin\Unit\Enqueuer;

use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Sylius\Component\Core\Model\Product;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Locale\Model\LocaleInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Tests\Webgriffe\SyliusMailchimpPlugin\Entity\Channel\Channel;
use Webgriffe\SyliusMailchimpPlugin\Enqueuer\ProductEnqueuer;
use Webgriffe\SyliusMailchimpPlugin\Message\Product\ProductCreate;
use Webgriffe\SyliusMailchimpPlugin\Message\Product\ProductRemove;
use Webgriffe\SyliusMailchimpPlugin\Message\Product\ProductUpdate;

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
        $channel = $this->createChannel(id: 1);
        $product = $this->createProduct(id: 10, channels: [$channel]);

        $this->messageBus->expects($this->once())
            ->method('dispatch')
            ->with($this->isInstanceOf(ProductCreate::class))
            ->willReturnCallback(fn (object $msg) => new Envelope($msg));

        $this->enqueuer->enqueue($product, isNew: true);
    }

    public function testEnqueueExistingProductDispatchesProductUpdate(): void
    {
        $channel = $this->createChannel(id: 1);
        $product = $this->createProduct(id: 10, channels: [$channel]);

        $this->messageBus->expects($this->once())
            ->method('dispatch')
            ->with($this->isInstanceOf(ProductUpdate::class))
            ->willReturnCallback(fn (object $msg) => new Envelope($msg));

        $this->enqueuer->enqueue($product, isNew: false);
    }

    public function testEnqueueDispatchesForEachMailchimpChannel(): void
    {
        $channel1 = $this->createChannel(id: 1);
        $channel2 = $this->createChannel(id: 2);
        $product = $this->createProduct(id: 5, channels: [$channel1, $channel2]);

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

    private function createProduct(int $id, array $channels = []): ProductInterface
    {
        $product = new Product();
        self::setId($product, $id);
        $product->setCode('PROD-' . $id);
        foreach ($channels as $channel) {
            $product->addChannel($channel);
        }

        return $product;
    }

    private function createChannel(int $id): Channel
    {
        $locale = $this->createMock(LocaleInterface::class);
        $locale->method('getCode')->willReturn('en_US');

        $channel = new Channel();
        self::setId($channel, $id);
        $channel->setDefaultLocale($locale);

        return $channel;
    }

    private static function setId(object $entity, int $id): void
    {
        $ref = new \ReflectionProperty($entity, 'id');
        $ref->setValue($entity, $id);
    }
}
