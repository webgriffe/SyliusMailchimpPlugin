<?php

declare(strict_types=1);

namespace Webgriffe\SyliusMailchimpPlugin\Enqueuer;

use Psr\Log\LoggerInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Webgriffe\SyliusMailchimpPlugin\Mapper\StoreMapper;
use Webgriffe\SyliusMailchimpPlugin\Message\Store\StoreCreate;
use Webgriffe\SyliusMailchimpPlugin\Model\ChannelMailchimpAwareInterface;

final class StoreEnqueuer
{
    public function __construct(
        private readonly MessageBusInterface $messageBus,
        private readonly StoreMapper $storeMapper,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function enqueue(ChannelInterface&ChannelMailchimpAwareInterface $channel): void
    {
        $channelId = $channel->getId();
        if (!is_int($channelId)) {
            $this->logger->warning('[Mailchimp] Channel has no integer ID, skipping StoreEnqueuer.');

            return;
        }

        $store = $this->storeMapper->map($channel);
        $this->logger->info('[Mailchimp] Enqueueing store sync for channel #{id} (store {store}).', [
            'id' => $channelId,
            'store' => $store->id,
        ]);
        $this->messageBus->dispatch(new StoreCreate($channelId));
    }
}
