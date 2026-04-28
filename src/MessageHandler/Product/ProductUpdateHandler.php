<?php

declare(strict_types=1);

namespace Webgriffe\SyliusMailchimpPlugin\MessageHandler\Product;

use Psr\Log\LoggerInterface;
use Sylius\Component\Channel\Repository\ChannelRepositoryInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Repository\ProductRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Webgriffe\SyliusMailchimpPlugin\Client\MailchimpClientInterface;
use Webgriffe\SyliusMailchimpPlugin\Mapper\ProductMapper;
use Webgriffe\SyliusMailchimpPlugin\Mapper\StoreMapper;
use Webgriffe\SyliusMailchimpPlugin\Message\Product\ProductUpdate;
use Webgriffe\SyliusMailchimpPlugin\Model\ChannelMailchimpAwareInterface;

#[AsMessageHandler]
final class ProductUpdateHandler
{
    public function __construct(
        private readonly ProductRepositoryInterface $productRepository,
        private readonly ChannelRepositoryInterface $channelRepository,
        private readonly ProductMapper $productMapper,
        private readonly StoreMapper $storeMapper,
        private readonly MailchimpClientInterface $mailchimpClient,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(ProductUpdate $message): void
    {
        $product = $this->productRepository->find($message->productId);
        if (!$product instanceof ProductInterface) {
            $this->logger->warning('[Mailchimp] Product #{id} not found, skipping ProductUpdate.', ['id' => $message->productId]);

            return;
        }

        $channel = $this->channelRepository->find($message->channelId);
        if (!$channel instanceof ChannelInterface || !$channel instanceof ChannelMailchimpAwareInterface) {
            $this->logger->warning('[Mailchimp] Channel #{id} not found or not Mailchimp-aware, skipping ProductUpdate.', ['id' => $message->channelId]);

            return;
        }

        $store = $this->storeMapper->map($channel);
        $mappedProduct = $this->productMapper->map($product, $channel, $message->locale);
        $this->mailchimpClient->upsertProduct($store->id, $mappedProduct);
        $this->logger->info('[Mailchimp] Product #{id} updated in store {store}.', ['id' => $message->productId, 'store' => $store->id]);
    }
}
