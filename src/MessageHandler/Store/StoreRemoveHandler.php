<?php

declare(strict_types=1);

namespace Webgriffe\SyliusMailchimpPlugin\MessageHandler\Store;

use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Webgriffe\SyliusMailchimpPlugin\Client\MailchimpClientInterface;
use Webgriffe\SyliusMailchimpPlugin\Message\Store\StoreRemove;

#[AsMessageHandler]
final class StoreRemoveHandler
{
    public function __construct(
        private readonly MailchimpClientInterface $mailchimpClient,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(StoreRemove $message): void
    {
        try {
            $this->mailchimpClient->removeStore($message->storeId);
            $this->logger->info('[Mailchimp] Store removed: {id}.', ['id' => $message->storeId]);
        } catch (\Throwable $e) {
            $this->logger->error('[Mailchimp] Failed to remove store {id}: {msg}', [
                'id' => $message->storeId,
                'msg' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
