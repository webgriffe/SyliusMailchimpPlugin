<?php

declare(strict_types=1);

namespace Webgriffe\SyliusMailchimpPlugin\Enqueuer;

use Psr\Log\LoggerInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Webgriffe\SyliusMailchimpPlugin\Message\Cart\CartCreate;
use Webgriffe\SyliusMailchimpPlugin\Message\Cart\CartRemove;
use Webgriffe\SyliusMailchimpPlugin\Message\Cart\CartUpdate;
use Webgriffe\SyliusMailchimpPlugin\Model\ChannelMailchimpAwareInterface;
use Webgriffe\SyliusMailchimpPlugin\Model\MailchimpOrderAwareInterface;
use Webgriffe\SyliusMailchimpPlugin\Provider\AudienceProviderInterface;
use Webgriffe\SyliusMailchimpPlugin\Resolver\StoreIdentifierResolverInterface;

final class CartEnqueuer implements CartEnqueuerInterface
{
    public function __construct(
        private readonly MessageBusInterface $messageBus,
        private readonly LoggerInterface $logger,
        private readonly AudienceProviderInterface $audienceProvider,
        private readonly StoreIdentifierResolverInterface $storeIdentifierResolver,
    ) {
    }

    #[\Override]
    public function enqueue(OrderInterface $order): void
    {
        $orderId = $order->getId();
        if (!is_int($orderId)) {
            $this->logger->warning('[Mailchimp] Order has no integer ID, skipping CartEnqueuer.');

            return;
        }

        $channel = $order->getChannel();
        if (!$channel instanceof ChannelInterface || !$channel instanceof ChannelMailchimpAwareInterface) {
            $this->logger->warning('[Mailchimp] Order #{id} has no Mailchimp-aware channel, skipping CartEnqueuer.', ['id' => $orderId]);

            return;
        }

        try {
            if ($order instanceof MailchimpOrderAwareInterface && $order->getMailchimpCartId() !== null) {
                $this->messageBus->dispatch(new CartUpdate($orderId), [new DelayStamp(1000)]);
                $this->logger->debug('[Mailchimp] Dispatched CartUpdate for order #{id}.', ['id' => $orderId]);
            } else {
                $this->messageBus->dispatch(new CartCreate($orderId), [new DelayStamp(1000)]);
                $this->logger->debug('[Mailchimp] Dispatched CartCreate for order #{id}.', ['id' => $orderId]);
            }
        } catch (\Throwable $e) {
            $this->logger->error('[Mailchimp] Failed to enqueue cart sync for order #{id}: {msg}', ['id' => $orderId, 'msg' => $e->getMessage()]);
        }
    }

    #[\Override]
    public function enqueueRemoval(OrderInterface $order): void
    {
        if (!$order instanceof MailchimpOrderAwareInterface) {
            return;
        }

        $cartId = $order->getMailchimpCartId();
        if ($cartId === null || $cartId === '') {
            return;
        }

        $channel = $order->getChannel();
        if (!$channel instanceof ChannelInterface || !$channel instanceof ChannelMailchimpAwareInterface) {
            return;
        }

        try {
            $audience = $this->audienceProvider->getAudience($channel, $order->getLocaleCode());
            $storeId = $this->storeIdentifierResolver->resolve($audience);
            $this->logger->debug('[Mailchimp] Dispatching CartRemove for cart {cartId} in store {store}.', ['cartId' => $cartId, 'store' => $storeId]);
            $this->messageBus->dispatch(new CartRemove($storeId, $cartId), [new DelayStamp(1000)]);
        } catch (\Throwable $e) {
            $this->logger->error('[Mailchimp] Failed to enqueue cart removal for cart {cartId}: {msg}', ['cartId' => $cartId, 'msg' => $e->getMessage()]);
        }
    }
}
