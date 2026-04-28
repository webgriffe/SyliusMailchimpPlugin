<?php

declare(strict_types=1);

namespace Webgriffe\SyliusMailchimpPlugin\MessageHandler\Newsletter;

use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Webgriffe\SyliusMailchimpPlugin\Client\Exception\ComplianceStateException;
use Webgriffe\SyliusMailchimpPlugin\Client\MailchimpClientInterface;
use Webgriffe\SyliusMailchimpPlugin\Message\Newsletter\NewsletterSubscribe;
use Webgriffe\SyliusMailchimpPlugin\ValueObject\Member;
use Webgriffe\SyliusMailchimpPlugin\ValueObject\MergeFields;

#[AsMessageHandler]
final class NewsletterSubscribeHandler
{
    public function __construct(
        private readonly MailchimpClientInterface $mailchimpClient,
        private readonly string $memberDefaultStatus,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(NewsletterSubscribe $message): void
    {
        $member = new Member(
            emailAddress: $message->email,
            status: $this->memberDefaultStatus,
            mergeFields: new MergeFields('', ''),
            tags: [],
            interests: [],
            language: '',
            ipSignup: '',
        );

        try {
            $this->mailchimpClient->upsertMember($message->listId, $member);
            $this->logger->info('[Mailchimp] Newsletter subscribed: {email} to list {list}.', [
                'email' => $message->email,
                'list' => $message->listId,
            ]);
        } catch (ComplianceStateException $e) {
            $this->logger->warning('[Mailchimp] Newsletter compliance state for {email}: {msg}', [
                'email' => $message->email,
                'msg' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
