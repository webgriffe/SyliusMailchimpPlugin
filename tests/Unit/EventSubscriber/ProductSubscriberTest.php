<?php

declare(strict_types=1);

namespace Tests\Webgriffe\SyliusMailchimpPlugin\Unit\EventSubscriber;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\Product;
use Symfony\Component\EventDispatcher\GenericEvent;
use Tests\Webgriffe\SyliusMailchimpPlugin\Entity\Channel\Channel;
use Webgriffe\SyliusMailchimpPlugin\Enqueuer\ProductEnqueuerInterface;
use Webgriffe\SyliusMailchimpPlugin\EventSubscriber\ProductSubscriber;
use Webgriffe\SyliusMailchimpPlugin\Provider\AudienceProviderInterface;
use Webgriffe\SyliusMailchimpPlugin\Resolver\StoreIdentifierResolverInterface;
use Webgriffe\SyliusMailchimpPlugin\ValueObject\Audience;

final class ProductSubscriberTest extends TestCase
{
    private MockObject&ProductEnqueuerInterface $productEnqueuer;

    private MockObject&AudienceProviderInterface $audienceProvider;

    private MockObject&StoreIdentifierResolverInterface $storeIdentifierResolver;

    private ProductSubscriber $subscriber;

    protected function setUp(): void
    {
        $this->productEnqueuer = $this->createMock(ProductEnqueuerInterface::class);
        $this->audienceProvider = $this->createMock(AudienceProviderInterface::class);
        $this->storeIdentifierResolver = $this->createMock(StoreIdentifierResolverInterface::class);
        $this->subscriber = new ProductSubscriber(
            $this->productEnqueuer,
            new NullLogger(),
            $this->audienceProvider,
            $this->storeIdentifierResolver,
        );
    }

    public function test_get_subscribed_events(): void
    {
        $events = ProductSubscriber::getSubscribedEvents();

        self::assertArrayHasKey('sylius.product.post_create', $events);
        self::assertArrayHasKey('sylius.product.post_update', $events);
        self::assertArrayHasKey('sylius.product.pre_delete', $events);
    }

    public function test_on_product_post_create_enqueues_as_new(): void
    {
        $product = new Product();

        $this->productEnqueuer->expects(self::once())->method('enqueue')->with($product, isNew: true);

        $this->subscriber->onProductPostCreate(new GenericEvent($product));
    }

    public function test_on_product_post_update_enqueues_as_not_new(): void
    {
        $product = new Product();

        $this->productEnqueuer->expects(self::once())->method('enqueue')->with($product, isNew: false);

        $this->subscriber->onProductPostUpdate(new GenericEvent($product));
    }

    public function test_on_product_post_create_ignores_non_product_subject(): void
    {
        $this->productEnqueuer->expects(self::never())->method('enqueue');

        $this->subscriber->onProductPostCreate(new GenericEvent(new \stdClass()));
    }

    public function test_on_product_post_update_ignores_non_product_subject(): void
    {
        $this->productEnqueuer->expects(self::never())->method('enqueue');

        $this->subscriber->onProductPostUpdate(new GenericEvent(new \stdClass()));
    }

    public function test_on_product_pre_delete_enqueues_removal_for_each_mailchimp_channel(): void
    {
        $channel1 = new Channel();
        $channel1->setCode('CHANNEL_1');
        $channel1->setMailchimpAudienceId('aud1');
        $channel2 = new Channel();
        $channel2->setCode('CHANNEL_2');
        $channel2->setMailchimpAudienceId('aud2');

        $product = new Product();
        $product->addChannel($channel1);
        $product->addChannel($channel2);

        $this->audienceProvider->method('getAudience')
            ->willReturnCallback(static fn (Channel $ch) => new Audience((string) $ch->getMailchimpAudienceId(), $ch));
        $this->storeIdentifierResolver->method('resolve')
            ->willReturnCallback(static fn (Audience $a) => sprintf('%s-%s', (string) $a->channel->getCode(), $a->id));

        $this->productEnqueuer->expects(self::exactly(2))->method('buildProductId')->with($product)->willReturn('prod-123');
        $this->productEnqueuer->expects(self::exactly(2))->method('enqueueRemoval');

        $this->subscriber->onProductPreDelete(new GenericEvent($product));
    }

    public function test_on_product_pre_delete_skips_non_mailchimp_channels(): void
    {
        $channel = $this->createMock(ChannelInterface::class);

        $product = new Product();
        $product->addChannel($channel);

        $this->audienceProvider->expects(self::never())->method('getAudience');
        $this->productEnqueuer->expects(self::never())->method('enqueueRemoval');

        $this->subscriber->onProductPreDelete(new GenericEvent($product));
    }

    public function test_on_product_pre_delete_ignores_non_product_subject(): void
    {
        $this->productEnqueuer->expects(self::never())->method('enqueueRemoval');

        $this->subscriber->onProductPreDelete(new GenericEvent(new \stdClass()));
    }
}
