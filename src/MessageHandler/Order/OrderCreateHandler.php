<?php

declare(strict_types=1);

namespace Webgriffe\SyliusMailchimpPlugin\MessageHandler\Order;

use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Model\ProductVariantInterface;
use Sylius\Component\Core\Repository\OrderRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Webgriffe\SyliusMailchimpPlugin\Client\MailchimpClientInterface;
use Webgriffe\SyliusMailchimpPlugin\Message\Order\OrderCreate;
use Webgriffe\SyliusMailchimpPlugin\Model\ChannelMailchimpAwareInterface;
use Webgriffe\SyliusMailchimpPlugin\Model\MailchimpOrderAwareInterface;
use Webgriffe\SyliusMailchimpPlugin\Provider\AudienceProviderInterface;
use Webgriffe\SyliusMailchimpPlugin\Resolver\StoreIdentifierResolverInterface;
use Webgriffe\SyliusMailchimpPlugin\Util\IdSanitizer;
use Webgriffe\SyliusMailchimpPlugin\Util\MailchimpErrorClassifier;

#[AsMessageHandler]
final class OrderCreateHandler
{
    public function __construct(
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly AudienceProviderInterface $audienceProvider,
        private readonly StoreIdentifierResolverInterface $storeIdentifierResolver,
        private readonly MailchimpClientInterface $mailchimpClient,
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(OrderCreate $message): void
    {
        $order = $this->orderRepository->find($message->orderId);
        if (!$order instanceof OrderInterface || !$order instanceof MailchimpOrderAwareInterface) {
            $this->logger->warning('[Mailchimp] Order #{id} not found or not Mailchimp-aware, skipping OrderCreate.', ['id' => $message->orderId]);

            return;
        }

        $channel = $order->getChannel();
        if (!$channel instanceof ChannelInterface || !$channel instanceof ChannelMailchimpAwareInterface) {
            $this->logger->warning('[Mailchimp] Order #{id} has no Mailchimp-aware channel, skipping OrderCreate.', ['id' => $message->orderId]);

            return;
        }

        try {
            $locale = $order->getLocaleCode() ?? $channel->getDefaultLocale()?->getCode() ?? 'en';
            $audience = $this->audienceProvider->getAudience($channel, $locale);
            $storeId = $this->storeIdentifierResolver->resolve($audience);
            $this->upsertOrderProducts($order, $storeId, $channel, $locale);

            $this->mailchimpClient->upsertOrder($storeId, $order, $message->isInRealTime);
            $order->setMailchimpOrderId(IdSanitizer::sanitize((string) $order->getId()));
            $order->setMailchimpOrderError(null);
            $this->entityManager->flush();
            $this->logger->info('[Mailchimp] Order created for order #{id} in store {store}.', ['id' => $message->orderId, 'store' => $storeId]);
        } catch (\Throwable $e) {
            $this->logger->error('[Mailchimp] Failed to sync order #{id}: {msg}', ['id' => $message->orderId, 'msg' => $e->getMessage()]);
            if (!MailchimpErrorClassifier::isPermanent($e)) {
                throw $e;
            }
            $this->persistError($order, $e);
        }
    }

    private function persistError(MailchimpOrderAwareInterface $order, \Throwable $e): void
    {
        try {
            $order->setMailchimpOrderError($e->getMessage());
            $this->entityManager->flush();
        } catch (\Throwable $flushError) {
            $this->logger->error('[Mailchimp] Could not persist order sync error: {msg}', ['msg' => $flushError->getMessage()]);
        }
    }

    private function upsertOrderProducts(OrderInterface $order, string $storeId, ChannelInterface $channel, string $locale): void
    {
        $syncedProductIds = [];
        foreach ($order->getItems() as $item) {
            $variant = $item->getVariant();
            if (!$variant instanceof ProductVariantInterface) {
                continue;
            }

            $product = $variant->getProduct();
            if (!$product instanceof ProductInterface) {
                continue;
            }

            $productId = $product->getId();
            if ($productId === null || isset($syncedProductIds[$productId])) {
                continue;
            }

            $this->mailchimpClient->upsertProduct($storeId, $product, $channel, $locale);
            $syncedProductIds[$productId] = true;
            $this->logger->debug('[Mailchimp] Upserted product #{id} before OrderCreate.', ['id' => $productId]);
        }
    }
}
