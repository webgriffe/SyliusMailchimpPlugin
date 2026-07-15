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
use Webgriffe\SyliusMailchimpPlugin\Message\Product\ProductCreate;
use Webgriffe\SyliusMailchimpPlugin\Model\ChannelMailchimpAwareInterface;
use Webgriffe\SyliusMailchimpPlugin\Provider\AudienceProviderInterface;
use Webgriffe\SyliusMailchimpPlugin\Resolver\StoreIdentifierResolverInterface;

#[AsMessageHandler]
final class ProductCreateHandler
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

    public function __invoke(ProductCreate $message): void
    {
        $product = $this->productRepository->find($message->productId);
        if (!$product instanceof ProductInterface) {
            $this->logger->warning('[Mailchimp] Product #{id} not found, skipping ProductCreate.', ['id' => $message->productId]);

            return;
        }

        $channel = $this->channelRepository->find($message->channelId);
        if (!$channel instanceof ChannelInterface || !$channel instanceof ChannelMailchimpAwareInterface) {
            $this->logger->warning('[Mailchimp] Channel #{id} not found or not Mailchimp-aware, skipping ProductCreate.', ['id' => $message->channelId]);

            return;
        }

        $audience = $this->audienceProvider->getAudience($channel, $message->locale);
        $storeId = $this->storeIdentifierResolver->resolve($audience);
        $this->mailchimpClient->upsertProduct($storeId, $product, $channel, $message->locale);
        $this->logger->info('[Mailchimp] Product #{id} created in store {store}.', ['id' => $message->productId, 'store' => $storeId]);
    }
}
