<?php

declare(strict_types=1);

namespace Webgriffe\SyliusMailchimpPlugin\Enqueuer;

use Psr\Log\LoggerInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Webgriffe\SyliusMailchimpPlugin\Message\Order\OrderCreate;
use Webgriffe\SyliusMailchimpPlugin\Message\Order\OrderRemove;
use Webgriffe\SyliusMailchimpPlugin\Message\Order\OrderUpdate;
use Webgriffe\SyliusMailchimpPlugin\Model\ChannelMailchimpAwareInterface;
use Webgriffe\SyliusMailchimpPlugin\Model\MailchimpOrderAwareInterface;
use Webgriffe\SyliusMailchimpPlugin\Util\IdSanitizer;

final class OrderEnqueuer implements OrderEnqueuerInterface
{
    public function __construct(
        private readonly MessageBusInterface $messageBus,
        private readonly LoggerInterface $logger,
    ) {
    }

    #[\Override]
    public function enqueue(OrderInterface $order, bool $isInRealTime = false): void
    {
        $orderId = $order->getId();
        if (!is_int($orderId)) {
            $this->logger->warning('[Mailchimp] Order has no integer ID, skipping OrderEnqueuer.');

            return;
        }

        $channel = $order->getChannel();
        if (!$channel instanceof ChannelInterface || !$channel instanceof ChannelMailchimpAwareInterface) {
            $this->logger->warning('[Mailchimp] Order #{id} has no Mailchimp-aware channel, skipping OrderEnqueuer.', ['id' => $orderId]);

            return;
        }

        $channelId = $channel->getId();
        if (!is_int($channelId)) {
            return;
        }

        if ($order instanceof MailchimpOrderAwareInterface && $order->getMailchimpOrderId() !== null) {
            $this->messageBus->dispatch(new OrderUpdate($orderId, $channelId));
        } else {
            $this->messageBus->dispatch(new OrderCreate($orderId, $channelId, $isInRealTime));
        }
    }

    #[\Override]
    public function enqueueRemoval(OrderInterface $order): void
    {
        if (!$order instanceof MailchimpOrderAwareInterface) {
            return;
        }

        $mailchimpOrderId = $order->getMailchimpOrderId();
        if ($mailchimpOrderId === null || $mailchimpOrderId === '') {
            return;
        }

        $channel = $order->getChannel();
        if (!$channel instanceof ChannelInterface || !$channel instanceof ChannelMailchimpAwareInterface) {
            return;
        }

        $storeId = IdSanitizer::sanitize((string) $channel->getCode());
        $this->messageBus->dispatch(new OrderRemove($storeId, $mailchimpOrderId));
    }
}
