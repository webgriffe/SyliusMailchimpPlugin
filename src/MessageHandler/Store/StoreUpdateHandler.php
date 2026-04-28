<?php

declare(strict_types=1);

namespace Webgriffe\SyliusMailchimpPlugin\MessageHandler\Store;

use Psr\Log\LoggerInterface;
use Sylius\Component\Channel\Repository\ChannelRepositoryInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Webgriffe\SyliusMailchimpPlugin\Client\MailchimpClientInterface;
use Webgriffe\SyliusMailchimpPlugin\Mapper\StoreMapper;
use Webgriffe\SyliusMailchimpPlugin\Message\Store\StoreUpdate;
use Webgriffe\SyliusMailchimpPlugin\Model\ChannelMailchimpAwareInterface;

#[AsMessageHandler]
final class StoreUpdateHandler
{
    public function __construct(
        private readonly ChannelRepositoryInterface $channelRepository,
        private readonly StoreMapper $storeMapper,
        private readonly MailchimpClientInterface $mailchimpClient,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(StoreUpdate $message): void
    {
        $channel = $this->channelRepository->find($message->channelId);
        if (!$channel instanceof ChannelInterface || !$channel instanceof ChannelMailchimpAwareInterface) {
            $this->logger->warning('[Mailchimp] Channel #{id} not found or not Mailchimp-aware, skipping StoreUpdate.', ['id' => $message->channelId]);

            return;
        }

        $store = $this->storeMapper->map($channel);
        $this->mailchimpClient->upsertStore($store->id, $store);
        $this->logger->info('[Mailchimp] Store updated for channel #{id}.', ['id' => $message->channelId]);
    }
}
