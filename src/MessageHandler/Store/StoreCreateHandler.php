<?php

declare(strict_types=1);

namespace Webgriffe\SyliusMailchimpPlugin\MessageHandler\Store;

use Psr\Log\LoggerInterface;
use Sylius\Component\Channel\Repository\ChannelRepositoryInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Webgriffe\SyliusMailchimpPlugin\Client\MailchimpClientInterface;
use Webgriffe\SyliusMailchimpPlugin\Mapper\StoreMapper;
use Webgriffe\SyliusMailchimpPlugin\Message\Store\StoreCreate;
use Webgriffe\SyliusMailchimpPlugin\Model\ChannelMailchimpAwareInterface;

#[AsMessageHandler]
final class StoreCreateHandler
{
    public function __construct(
        private readonly ChannelRepositoryInterface $channelRepository,
        private readonly StoreMapper $storeMapper,
        private readonly MailchimpClientInterface $mailchimpClient,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(StoreCreate $message): void
    {
        $channel = $this->channelRepository->find($message->channelId);
        if (!$channel instanceof ChannelInterface || !$channel instanceof ChannelMailchimpAwareInterface) {
            $this->logger->warning('[Mailchimp] Channel #{id} not found or not Mailchimp-aware, skipping StoreCreate.', ['id' => $message->channelId]);

            return;
        }

        $store = $this->storeMapper->map($channel);
        $this->mailchimpClient->upsertStore($store->id, $store);
        $this->logger->info('[Mailchimp] Store created/updated for channel #{id}.', ['id' => $message->channelId]);
    }
}
