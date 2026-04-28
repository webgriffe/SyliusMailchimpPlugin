<?php

declare(strict_types=1);

namespace Webgriffe\SyliusMailchimpPlugin\MessageHandler\Member;

use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Webgriffe\SyliusMailchimpPlugin\Client\MailchimpClientInterface;
use Webgriffe\SyliusMailchimpPlugin\Message\Member\MemberRemove;

#[AsMessageHandler]
final class MemberRemoveHandler
{
    public function __construct(
        private readonly MailchimpClientInterface $mailchimpClient,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(MemberRemove $message): void
    {
        try {
            $this->mailchimpClient->removeMember($message->listId, $message->subscriberHash);
            $this->logger->info('[Mailchimp] Member removed: hash={hash}.', ['hash' => $message->subscriberHash]);
        } catch (\Throwable $e) {
            $this->logger->error('[Mailchimp] Failed to remove member hash={hash}: {msg}', [
                'hash' => $message->subscriberHash,
                'msg' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
