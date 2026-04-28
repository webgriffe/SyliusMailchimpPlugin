<?php

declare(strict_types=1);

namespace Webgriffe\SyliusMailchimpPlugin\Enqueuer;

use Psr\Log\LoggerInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Webgriffe\SyliusMailchimpPlugin\Message\Cart\CartCreate;
use Webgriffe\SyliusMailchimpPlugin\Message\Cart\CartRemove;
use Webgriffe\SyliusMailchimpPlugin\Message\Cart\CartUpdate;
use Webgriffe\SyliusMailchimpPlugin\Model\ChannelMailchimpAwareInterface;
use Webgriffe\SyliusMailchimpPlugin\Model\MailchimpOrderAwareInterface;
use Webgriffe\SyliusMailchimpPlugin\Util\IdSanitizer;

final class CartEnqueuer
{
    public function __construct(
        private readonly MessageBusInterface $messageBus,
        private readonly LoggerInterface $logger,
    ) {
    }

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

        $channelId = $channel->getId();
        if (!is_int($channelId)) {
            return;
        }

        if ($order instanceof MailchimpOrderAwareInterface && $order->getMailchimpCartId() !== null) {
            $this->messageBus->dispatch(new CartUpdate($orderId, $channelId));
        } else {
            $this->messageBus->dispatch(new CartCreate($orderId, $channelId));
        }
    }

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

        $storeId = IdSanitizer::sanitize((string) $channel->getCode());
        $this->messageBus->dispatch(new CartRemove($storeId, $cartId));
    }
}
