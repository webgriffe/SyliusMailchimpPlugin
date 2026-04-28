<?php

declare(strict_types=1);

namespace Webgriffe\SyliusMailchimpPlugin\MessageHandler\Cart;

use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Webgriffe\SyliusMailchimpPlugin\Client\MailchimpClientInterface;
use Webgriffe\SyliusMailchimpPlugin\Message\Cart\CartRemove;

#[AsMessageHandler]
final class CartRemoveHandler
{
    public function __construct(
        private readonly MailchimpClientInterface $mailchimpClient,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(CartRemove $message): void
    {
        try {
            $this->mailchimpClient->removeCart($message->storeId, $message->cartId);
            $this->logger->info('[Mailchimp] Cart {id} removed from store {store}.', [
                'id' => $message->cartId,
                'store' => $message->storeId,
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('[Mailchimp] Failed to remove cart {id} from store {store}: {msg}', [
                'id' => $message->cartId,
                'store' => $message->storeId,
                'msg' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
