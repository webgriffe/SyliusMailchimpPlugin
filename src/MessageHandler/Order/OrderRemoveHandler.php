<?php

declare(strict_types=1);

namespace Webgriffe\SyliusMailchimpPlugin\MessageHandler\Order;

use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Webgriffe\SyliusMailchimpPlugin\Client\MailchimpClientInterface;
use Webgriffe\SyliusMailchimpPlugin\Message\Order\OrderRemove;

#[AsMessageHandler]
final class OrderRemoveHandler
{
    public function __construct(
        private readonly MailchimpClientInterface $mailchimpClient,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(OrderRemove $message): void
    {
        try {
            $this->mailchimpClient->removeOrder($message->storeId, $message->orderId);
            $this->logger->info('[Mailchimp] Order {id} removed from store {store}.', [
                'id' => $message->orderId,
                'store' => $message->storeId,
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('[Mailchimp] Failed to remove order {id} from store {store}: {msg}', [
                'id' => $message->orderId,
                'store' => $message->storeId,
                'msg' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
