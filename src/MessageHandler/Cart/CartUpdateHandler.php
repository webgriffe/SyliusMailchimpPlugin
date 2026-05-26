<?php

declare(strict_types=1);

namespace Webgriffe\SyliusMailchimpPlugin\MessageHandler\Cart;

use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Sylius\Component\Channel\Repository\ChannelRepositoryInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Model\ProductVariantInterface;
use Sylius\Component\Core\Repository\OrderRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Webgriffe\SyliusMailchimpPlugin\Client\MailchimpClientInterface;
use Webgriffe\SyliusMailchimpPlugin\Mapper\CartMapper;
use Webgriffe\SyliusMailchimpPlugin\Mapper\ProductMapperInterface;
use Webgriffe\SyliusMailchimpPlugin\Message\Cart\CartUpdate;
use Webgriffe\SyliusMailchimpPlugin\Model\ChannelMailchimpAwareInterface;
use Webgriffe\SyliusMailchimpPlugin\Model\MailchimpOrderAwareInterface;
use Webgriffe\SyliusMailchimpPlugin\Provider\AudienceProviderInterface;
use Webgriffe\SyliusMailchimpPlugin\Resolver\StoreIdentifierResolverInterface;

#[AsMessageHandler]
final class CartUpdateHandler
{
    public function __construct(
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly ChannelRepositoryInterface $channelRepository,
        private readonly CartMapper $cartMapper,
        private readonly AudienceProviderInterface $audienceProvider,
        private readonly StoreIdentifierResolverInterface $storeIdentifierResolver,
        private readonly ProductMapperInterface $productMapper,
        private readonly MailchimpClientInterface $mailchimpClient,
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(CartUpdate $message): void
    {
        $order = $this->orderRepository->find($message->orderId);
        if (!$order instanceof OrderInterface || !$order instanceof MailchimpOrderAwareInterface) {
            $this->logger->warning('[Mailchimp] Order #{id} not found or not Mailchimp-aware, skipping CartUpdate.', ['id' => $message->orderId]);

            return;
        }

        $channel = $this->channelRepository->find($message->channelId);
        if (!$channel instanceof ChannelInterface || !$channel instanceof ChannelMailchimpAwareInterface) {
            $this->logger->warning('[Mailchimp] Channel #{id} not found or not Mailchimp-aware, skipping CartUpdate.', ['id' => $message->channelId]);

            return;
        }

        $locale = $order->getLocaleCode() ?? $channel->getDefaultLocale()?->getCode() ?? 'en';
        $audience = $this->audienceProvider->getAudience($channel, $locale);
        $storeId = $this->storeIdentifierResolver->resolve($audience);
        $this->upsertCartProducts($order, $storeId, $channel, $locale);

        $cart = $this->cartMapper->map($order, $channel);
        $this->mailchimpClient->upsertCart($storeId, $cart);
        $order->setMailchimpCartId($cart->id);
        $order->setMailchimpCartError(null);
        $this->entityManager->flush();
        $this->logger->info('[Mailchimp] Cart updated for order #{id} in store {store}.', ['id' => $message->orderId, 'store' => $storeId]);
    }

    private function upsertCartProducts(OrderInterface $order, string $storeId, ChannelInterface $channel, string $locale): void
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

            $mappedProduct = $this->productMapper->map($product, $channel, $locale);
            $this->mailchimpClient->upsertProduct($storeId, $mappedProduct);
            $syncedProductIds[$productId] = true;
            $this->logger->debug('[Mailchimp] Upserted product #{id} before CartUpdate.', ['id' => $productId]);
        }
    }
}
