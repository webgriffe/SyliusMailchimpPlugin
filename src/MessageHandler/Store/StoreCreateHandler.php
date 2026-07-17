<?php

declare(strict_types=1);

namespace Webgriffe\SyliusMailchimpPlugin\MessageHandler\Store;

use Psr\Log\LoggerInterface;
use Sylius\Component\Channel\Repository\ChannelRepositoryInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Webgriffe\SyliusMailchimpPlugin\Client\MailchimpClientInterface;
use Webgriffe\SyliusMailchimpPlugin\Message\Store\StoreCreate;
use Webgriffe\SyliusMailchimpPlugin\Model\ChannelMailchimpAwareInterface;
use Webgriffe\SyliusMailchimpPlugin\Provider\AudienceProviderInterface;
use Webgriffe\SyliusMailchimpPlugin\Util\MailchimpErrorClassifier;

#[AsMessageHandler]
final class StoreCreateHandler
{
    public function __construct(
        private readonly ChannelRepositoryInterface $channelRepository,
        private readonly AudienceProviderInterface $audienceProvider,
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

        try {
            $audience = $this->audienceProvider->getAudience($channel);
            $this->mailchimpClient->upsertStore($audience);
            $this->logger->info('[Mailchimp] Store created/updated for channel #{id}.', ['id' => $message->channelId]);
        } catch (\Throwable $e) {
            $this->logger->error('[Mailchimp] Failed to sync store for channel #{id}: {msg}', ['id' => $message->channelId, 'msg' => $e->getMessage()]);
            if (!MailchimpErrorClassifier::isPermanent($e)) {
                throw $e;
            }
        }
    }
}
