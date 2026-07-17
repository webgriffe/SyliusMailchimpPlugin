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
use Webgriffe\SyliusMailchimpPlugin\Message\Product\ProductUpdate;
use Webgriffe\SyliusMailchimpPlugin\Model\ChannelMailchimpAwareInterface;
use Webgriffe\SyliusMailchimpPlugin\Provider\AudienceProviderInterface;
use Webgriffe\SyliusMailchimpPlugin\Resolver\StoreIdentifierResolverInterface;
use Webgriffe\SyliusMailchimpPlugin\Util\MailchimpErrorClassifier;

#[AsMessageHandler]
final class ProductUpdateHandler
{
    public function __construct(
        private readonly ProductRepositoryInterface $productRepository,
        private readonly ChannelRepositoryInterface $channelRepository,
        private readonly AudienceProviderInterface $audienceProvider,
        private readonly StoreIdentifierResolverInterface $storeIdentifierResolver,
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

        try {
            $audience = $this->audienceProvider->getAudience($channel, $message->locale);
            $storeId = $this->storeIdentifierResolver->resolve($audience);
            $this->mailchimpClient->upsertProduct($storeId, $product, $channel, $message->locale);
            $this->logger->info('[Mailchimp] Product #{id} updated in store {store}.', ['id' => $message->productId, 'store' => $storeId]);
        } catch (\Throwable $e) {
            $this->logger->error('[Mailchimp] Failed to sync product #{id}: {msg}', ['id' => $message->productId, 'msg' => $e->getMessage()]);
            if (!MailchimpErrorClassifier::isPermanent($e)) {
                throw $e;
            }
        }
    }
}
