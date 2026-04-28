<?php

declare(strict_types=1);

namespace Tests\Webgriffe\SyliusMailchimpPlugin\Unit\Model;

use PHPUnit\Framework\TestCase;
use Webgriffe\SyliusMailchimpPlugin\Model\ChannelMailchimpAwareTrait;

final class ChannelMailchimpAwareTraitTest extends TestCase
{
    private object $subject;

    protected function setUp(): void
    {
        $this->subject = new class () {
            use ChannelMailchimpAwareTrait;
        };
    }

    public function test_mailchimp_audience_id_defaults_to_null(): void
    {
        self::assertNull($this->subject->getMailchimpAudienceId());
    }

    public function test_it_sets_and_gets_mailchimp_audience_id(): void
    {
        $this->subject->setMailchimpAudienceId('abc123xyz');

        self::assertSame('abc123xyz', $this->subject->getMailchimpAudienceId());
    }

    public function test_mailchimp_newsletter_positions_defaults_to_empty_array(): void
    {
        self::assertSame([], $this->subject->getMailchimpNewsletterPositions());
    }

    public function test_it_sets_and_gets_mailchimp_newsletter_positions(): void
    {
        $positions = ['checkout_addressing', 'register'];
        $this->subject->setMailchimpNewsletterPositions($positions);

        self::assertSame($positions, $this->subject->getMailchimpNewsletterPositions());
    }

    public function test_it_accepts_all_valid_newsletter_positions(): void
    {
        $positions = ['checkout_addressing', 'checkout_complete', 'register', 'my_account'];
        $this->subject->setMailchimpNewsletterPositions($positions);

        self::assertSame($positions, $this->subject->getMailchimpNewsletterPositions());
    }
}
