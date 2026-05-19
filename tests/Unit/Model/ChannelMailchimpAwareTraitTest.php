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
        $this->subject = new class() {
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
}
