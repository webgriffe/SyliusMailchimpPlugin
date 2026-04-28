<?php

declare(strict_types=1);

namespace Tests\Webgriffe\SyliusMailchimpPlugin\Unit\Model;

use PHPUnit\Framework\TestCase;
use Webgriffe\SyliusMailchimpPlugin\Model\MailchimpAwareTrait;

final class MailchimpAwareTraitTest extends TestCase
{
    private object $subject;

    protected function setUp(): void
    {
        $this->subject = new class () {
            use MailchimpAwareTrait;
        };
    }

    public function test_mailchimp_id_defaults_to_null(): void
    {
        self::assertNull($this->subject->getMailchimpId());
    }

    public function test_it_sets_and_gets_mailchimp_id(): void
    {
        $this->subject->setMailchimpId('abc123');

        self::assertSame('abc123', $this->subject->getMailchimpId());
    }

    public function test_it_sets_mailchimp_id_to_null(): void
    {
        $this->subject->setMailchimpId('abc123');
        $this->subject->setMailchimpId(null);

        self::assertNull($this->subject->getMailchimpId());
    }

    public function test_mailchimp_synced_at_defaults_to_null(): void
    {
        self::assertNull($this->subject->getMailchimpSyncedAt());
    }

    public function test_it_sets_and_gets_mailchimp_synced_at(): void
    {
        $date = new \DateTimeImmutable('2025-01-01 12:00:00');
        $this->subject->setMailchimpSyncedAt($date);

        self::assertSame($date, $this->subject->getMailchimpSyncedAt());
    }

    public function test_mailchimp_error_defaults_to_null(): void
    {
        self::assertNull($this->subject->getMailchimpError());
    }

    public function test_it_sets_and_gets_mailchimp_error(): void
    {
        $this->subject->setMailchimpError('Some API error');

        self::assertSame('Some API error', $this->subject->getMailchimpError());
    }
}
