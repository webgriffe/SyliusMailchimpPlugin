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
use Tests\Webgriffe\SyliusMailchimpPlugin\Unit\ReflectionIdTrait;
use Webgriffe\SyliusMailchimpPlugin\Enqueuer\ProductEnqueuer;
use Webgriffe\SyliusMailchimpPlugin\Message\Product\ProductCreate;
use Webgriffe\SyliusMailchimpPlugin\Message\Product\ProductRemove;
use Webgriffe\SyliusMailchimpPlugin\Message\Product\ProductUpdate;

final class ProductEnqueuerTest extends TestCase
{
    use ReflectionIdTrait;

    private MessageBusInterface $messageBus;

    private ProductEnqueuer $enqueuer;

    protected function setUp(): void
    {
        $this->messageBus = $this->createMock(MessageBusInterface::class);
        $this->enqueuer = new ProductEnqueuer($this->messageBus, new NullLogger());
    }

    public function test_enqueue_new_product_dispatches_product_create(): void
    {
        $channel = $this->createChannel(id: 1);
        $product = $this->createProduct(id: 10, channels: [$channel]);

        $this->messageBus->expects($this->once())
            ->method('dispatch')
            ->with($this->isInstanceOf(ProductCreate::class))
            ->willReturnCallback(fn (object $msg) => new Envelope($msg));

        $this->enqueuer->enqueue($product, isNew: true);
    }

    public function test_enqueue_existing_product_dispatches_product_update(): void
    {
        $channel = $this->createChannel(id: 1);
        $product = $this->createProduct(id: 10, channels: [$channel]);

        $this->messageBus->expects($this->once())
            ->method('dispatch')
            ->with($this->isInstanceOf(ProductUpdate::class))
            ->willReturnCallback(fn (object $msg) => new Envelope($msg));

        $this->enqueuer->enqueue($product, isNew: false);
    }

    public function test_enqueue_dispatches_for_each_mailchimp_channel(): void
    {
        $channel1 = $this->createChannel(id: 1);
        $channel2 = $this->createChannel(id: 2);
        $product = $this->createProduct(id: 5, channels: [$channel1, $channel2]);

        $this->messageBus->expects($this->exactly(2))
            ->method('dispatch')
            ->willReturnCallback(fn (object $msg) => new Envelope($msg));

        $this->enqueuer->enqueue($product);
    }

    public function test_enqueue_removal_dispatches_product_remove(): void
    {
        $this->messageBus->expects($this->once())
            ->method('dispatch')
            ->with($this->equalTo(new ProductRemove('WEB', 'TSHIRT')))
            ->willReturnCallback(fn (object $msg) => new Envelope($msg));

        $this->enqueuer->enqueueRemoval('WEB', 'TSHIRT');
    }

    public function test_enqueue_does_not_throw_when_dispatch_fails(): void
    {
        $channel = $this->createChannel(id: 1);
        $product = $this->createProduct(id: 10, channels: [$channel]);

        $this->messageBus->method('dispatch')->willThrowException(new \RuntimeException('Handler failed'));

        $this->enqueuer->enqueue($product);

        $this->expectNotToPerformAssertions();
    }

    private function createProduct(int $id, array $channels = []): ProductInterface
    {
        $product = new Product();
        self::setIdOnObject($product, $id);
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
        self::setIdOnObject($channel, $id);
        $channel->setDefaultLocale($locale);

        return $channel;
    }
}
