<?php

declare(strict_types=1);

namespace Webgriffe\SyliusMailchimpPlugin\MessageHandler\Product;

use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Webgriffe\SyliusMailchimpPlugin\Client\MailchimpClientInterface;
use Webgriffe\SyliusMailchimpPlugin\Message\Product\ProductRemove;

#[AsMessageHandler]
final class ProductRemoveHandler
{
    public function __construct(
        private readonly MailchimpClientInterface $mailchimpClient,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(ProductRemove $message): void
    {
        try {
            $this->mailchimpClient->removeProduct($message->storeId, $message->productId);
            $this->logger->info('[Mailchimp] Product {id} removed from store {store}.', [
                'id' => $message->productId,
                'store' => $message->storeId,
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('[Mailchimp] Failed to remove product {id} from store {store}: {msg}', [
                'id' => $message->productId,
                'store' => $message->storeId,
                'msg' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
