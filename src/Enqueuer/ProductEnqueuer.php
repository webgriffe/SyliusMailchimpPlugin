<?php

declare(strict_types=1);

namespace Webgriffe\SyliusMailchimpPlugin\Enqueuer;

use Psr\Log\LoggerInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Webgriffe\SyliusMailchimpPlugin\Message\Product\ProductCreate;
use Webgriffe\SyliusMailchimpPlugin\Message\Product\ProductRemove;
use Webgriffe\SyliusMailchimpPlugin\Message\Product\ProductUpdate;
use Webgriffe\SyliusMailchimpPlugin\Model\ChannelMailchimpAwareInterface;
use Webgriffe\SyliusMailchimpPlugin\Util\IdSanitizer;

final class ProductEnqueuer implements ProductEnqueuerInterface
{
    public function __construct(
        private readonly MessageBusInterface $messageBus,
        private readonly LoggerInterface $logger,
    ) {
    }

    #[\Override]
    public function enqueue(ProductInterface $product, bool $isNew = false): void
    {
        $productId = $product->getId();
        if (!is_int($productId)) {
            $this->logger->warning('[Mailchimp] Product has no integer ID, skipping ProductEnqueuer.');

            return;
        }

        foreach ($product->getChannels() as $channel) {
            if (!$channel instanceof ChannelInterface || !$channel instanceof ChannelMailchimpAwareInterface) {
                continue;
            }

            $channelId = $channel->getId();
            if (!is_int($channelId)) {
                continue;
            }

            $locale = $channel->getDefaultLocale()?->getCode() ?? 'en'; // todo: en is not a valid fallback
            $message = $isNew ? new ProductCreate($productId, $channelId, $locale) : new ProductUpdate($productId, $channelId, $locale);
            $this->logger->debug('[Mailchimp] Dispatching {type} for product #{id} in channel #{channel}.', [
                'type' => $isNew ? 'ProductCreate' : 'ProductUpdate',
                'id' => $productId,
                'channel' => $channelId,
            ]);

            try {
                $this->messageBus->dispatch($message);
            } catch (\Throwable $e) {
                $this->logger->error('[Mailchimp] Failed to enqueue product sync for product #{id} in channel #{channel}: {msg}', ['id' => $productId, 'channel' => $channelId, 'msg' => $e->getMessage()]);
            }
        }
    }

    #[\Override]
    public function enqueueRemoval(string $storeId, string $productId): void
    {
        try {
            $this->logger->debug('[Mailchimp] Dispatching ProductRemove for product {id} in store {store}.', ['id' => $productId, 'store' => $storeId]);
            $this->messageBus->dispatch(new ProductRemove($storeId, $productId));
        } catch (\Throwable $e) {
            $this->logger->error('[Mailchimp] Failed to enqueue product removal for product {id}: {msg}', ['id' => $productId, 'msg' => $e->getMessage()]);
        }
    }

    #[\Override]
    public function buildProductId(ProductInterface $product): string
    {
        return IdSanitizer::sanitize((string) $product->getCode());
    }
}
