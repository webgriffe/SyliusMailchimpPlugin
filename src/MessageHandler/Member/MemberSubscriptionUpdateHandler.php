<?php

declare(strict_types=1);

namespace Webgriffe\SyliusMailchimpPlugin\MessageHandler\Member;

use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Webgriffe\SyliusMailchimpPlugin\Message\Member\MemberSubscriptionUpdate;
use Webgriffe\SyliusMailchimpPlugin\Updater\MemberSubscriptionStatusUpdater;

#[AsMessageHandler]
final class MemberSubscriptionUpdateHandler
{
    public function __construct(
        private readonly MemberSubscriptionStatusUpdater $updater,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(MemberSubscriptionUpdate $message): void
    {
        $this->logger->info('[Mailchimp] Processing MemberSubscriptionUpdate type={type} for {email}.', [
            'type' => $message->type,
            'email' => $message->email,
        ]);

        $this->updater->update($message->type, $message->email, $message->listId);
    }
}
