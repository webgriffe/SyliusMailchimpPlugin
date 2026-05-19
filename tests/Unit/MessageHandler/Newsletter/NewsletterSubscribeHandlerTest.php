<?php

declare(strict_types=1);

namespace Tests\Webgriffe\SyliusMailchimpPlugin\Unit\MessageHandler\Newsletter;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Webgriffe\SyliusMailchimpPlugin\Client\Exception\ComplianceStateException;
use Webgriffe\SyliusMailchimpPlugin\Client\MailchimpClientInterface;
use Webgriffe\SyliusMailchimpPlugin\Message\Newsletter\NewsletterSubscribe;
use Webgriffe\SyliusMailchimpPlugin\MessageHandler\Newsletter\NewsletterSubscribeHandler;
use Webgriffe\SyliusMailchimpPlugin\ValueObject\Member;

final class NewsletterSubscribeHandlerTest extends TestCase
{
    private MockObject&MailchimpClientInterface $mailchimpClient;

    private NewsletterSubscribeHandler $handler;

    protected function setUp(): void
    {
        $this->mailchimpClient = $this->createMock(MailchimpClientInterface::class);
        $this->handler = new NewsletterSubscribeHandler(
            $this->mailchimpClient,
            'subscribed',
            new NullLogger(),
        );
    }

    public function test_upserts_member_with_default_status(): void
    {
        $message = new NewsletterSubscribe('user@example.com', 'list-abc');

        $this->mailchimpClient
            ->expects($this->once())
            ->method('upsertMember')
            ->with(
                'list-abc',
                $this->callback(static function (Member $member): bool {
                    return $member->emailAddress === 'user@example.com' &&
                        $member->status === 'subscribed';
                }),
            );

        ($this->handler)($message);
    }

    public function test_rethrows_compliance_state_exception(): void
    {
        $message = new NewsletterSubscribe('blocked@example.com', 'list-abc');
        $exception = new ComplianceStateException('Compliance error', 'blocked@example.com');

        $this->mailchimpClient
            ->method('upsertMember')
            ->willThrowException($exception);

        $this->expectException(ComplianceStateException::class);

        ($this->handler)($message);
    }

    public function test_uses_pending_status_when_configured(): void
    {
        $handler = new NewsletterSubscribeHandler(
            $this->mailchimpClient,
            'pending',
            new NullLogger(),
        );

        $this->mailchimpClient
            ->expects($this->once())
            ->method('upsertMember')
            ->with(
                'list-xyz',
                $this->callback(static function (Member $member): bool {
                    return $member->status === 'pending';
                }),
            );

        ($handler)(new NewsletterSubscribe('user@example.com', 'list-xyz'));
    }
}
