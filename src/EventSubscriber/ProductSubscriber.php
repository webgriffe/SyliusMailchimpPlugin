<?php

declare(strict_types=1);

namespace Webgriffe\SyliusMailchimpPlugin\EventSubscriber;

use Psr\Log\LoggerInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\EventDispatcher\GenericEvent;
use Webgriffe\SyliusMailchimpPlugin\Enqueuer\ProductEnqueuerInterface;
use Webgriffe\SyliusMailchimpPlugin\Model\ChannelMailchimpAwareInterface;
use Webgriffe\SyliusMailchimpPlugin\Util\IdSanitizer;

final class ProductSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly ProductEnqueuerInterface $productEnqueuer,
        private readonly LoggerInterface $logger,
    ) {
    }

    #[\Override]
    public static function getSubscribedEvents(): array
    {
        return [
            'sylius.product.post_create' => 'onProductPostCreate',
            'sylius.product.post_update' => 'onProductPostUpdate',
            'sylius.product.pre_delete' => 'onProductPreDelete',
        ];
    }

    public function onProductPostCreate(GenericEvent $event): void
    {
        $product = $event->getSubject();
        if (!$product instanceof ProductInterface) {
            return;
        }

        $this->logger->debug('[Mailchimp] onProductPostCreate: product #{id}.', ['id' => $product->getId()]);
        $this->productEnqueuer->enqueue($product, isNew: true);
    }

    public function onProductPostUpdate(GenericEvent $event): void
    {
        $product = $event->getSubject();
        if (!$product instanceof ProductInterface) {
            return;
        }

        $this->logger->debug('[Mailchimp] onProductPostUpdate: product #{id}.', ['id' => $product->getId()]);
        $this->productEnqueuer->enqueue($product, isNew: false);
    }

    public function onProductPreDelete(GenericEvent $event): void
    {
        $product = $event->getSubject();
        if (!$product instanceof ProductInterface) {
            return;
        }

        $this->logger->debug('[Mailchimp] onProductPreDelete: product #{id}.', ['id' => $product->getId()]);
        foreach ($product->getChannels() as $channel) {
            if (!$channel instanceof ChannelInterface || !$channel instanceof ChannelMailchimpAwareInterface) {
                continue;
            }

            $storeId = IdSanitizer::sanitize((string) $channel->getCode());
            $productId = $this->productEnqueuer->buildProductId($product);
            $this->productEnqueuer->enqueueRemoval($storeId, $productId);
        }
    }
}
