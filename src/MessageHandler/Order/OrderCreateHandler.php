<?php

declare(strict_types=1);

namespace Webgriffe\SyliusMailchimpPlugin\MessageHandler\Order;

use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Sylius\Component\Channel\Repository\ChannelRepositoryInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Repository\OrderRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Webgriffe\SyliusMailchimpPlugin\Client\MailchimpClientInterface;
use Webgriffe\SyliusMailchimpPlugin\Mapper\OrderMapper;
use Webgriffe\SyliusMailchimpPlugin\Mapper\StoreMapper;
use Webgriffe\SyliusMailchimpPlugin\Message\Order\OrderCreate;
use Webgriffe\SyliusMailchimpPlugin\Model\ChannelMailchimpAwareInterface;
use Webgriffe\SyliusMailchimpPlugin\Model\MailchimpOrderAwareInterface;

#[AsMessageHandler]
final class OrderCreateHandler
{
    public function __construct(
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly ChannelRepositoryInterface $channelRepository,
        private readonly OrderMapper $orderMapper,
        private readonly StoreMapper $storeMapper,
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

        $channel = $this->channelRepository->find($message->channelId);
        if (!$channel instanceof ChannelInterface || !$channel instanceof ChannelMailchimpAwareInterface) {
            $this->logger->warning('[Mailchimp] Channel #{id} not found or not Mailchimp-aware, skipping OrderCreate.', ['id' => $message->channelId]);

            return;
        }

        $store = $this->storeMapper->map($channel);
        $mappedOrder = $this->orderMapper->map($order, $message->isInRealTime);
        $this->mailchimpClient->upsertOrder($store->id, $mappedOrder);
        $order->setMailchimpOrderId($mappedOrder->id);
        $order->setMailchimpOrderError(null);
        $this->entityManager->flush();
        $this->logger->info('[Mailchimp] Order created for order #{id} in store {store}.', ['id' => $message->orderId, 'store' => $store->id]);
    }
}
