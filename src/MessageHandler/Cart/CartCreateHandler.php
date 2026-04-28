<?php

declare(strict_types=1);

namespace Webgriffe\SyliusMailchimpPlugin\MessageHandler\Cart;

use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Sylius\Component\Channel\Repository\ChannelRepositoryInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Repository\OrderRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Webgriffe\SyliusMailchimpPlugin\Client\MailchimpClientInterface;
use Webgriffe\SyliusMailchimpPlugin\Mapper\CartMapper;
use Webgriffe\SyliusMailchimpPlugin\Mapper\StoreMapper;
use Webgriffe\SyliusMailchimpPlugin\Message\Cart\CartCreate;
use Webgriffe\SyliusMailchimpPlugin\Model\ChannelMailchimpAwareInterface;
use Webgriffe\SyliusMailchimpPlugin\Model\MailchimpOrderAwareInterface;

#[AsMessageHandler]
final class CartCreateHandler
{
    public function __construct(
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly ChannelRepositoryInterface $channelRepository,
        private readonly CartMapper $cartMapper,
        private readonly StoreMapper $storeMapper,
        private readonly MailchimpClientInterface $mailchimpClient,
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(CartCreate $message): void
    {
        $order = $this->orderRepository->find($message->orderId);
        if (!$order instanceof OrderInterface || !$order instanceof MailchimpOrderAwareInterface) {
            $this->logger->warning('[Mailchimp] Order #{id} not found or not Mailchimp-aware, skipping CartCreate.', ['id' => $message->orderId]);

            return;
        }

        $channel = $this->channelRepository->find($message->channelId);
        if (!$channel instanceof ChannelInterface || !$channel instanceof ChannelMailchimpAwareInterface) {
            $this->logger->warning('[Mailchimp] Channel #{id} not found or not Mailchimp-aware, skipping CartCreate.', ['id' => $message->channelId]);

            return;
        }

        $store = $this->storeMapper->map($channel);
        $cart = $this->cartMapper->map($order, $channel);
        $this->mailchimpClient->upsertCart($store->id, $cart);
        $order->setMailchimpCartId($cart->id);
        $order->setMailchimpCartError(null);
        $this->entityManager->flush();
        $this->logger->info('[Mailchimp] Cart created for order #{id} in store {store}.', ['id' => $message->orderId, 'store' => $store->id]);
    }
}
