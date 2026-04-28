<?php

declare(strict_types=1);

namespace Tests\Webgriffe\SyliusMailchimpPlugin\Unit\Twig;

use PHPUnit\Framework\TestCase;
use Webgriffe\SyliusMailchimpPlugin\Model\MailchimpAwareInterface;
use Webgriffe\SyliusMailchimpPlugin\Twig\MailchimpRuntime;

final class MailchimpRuntimeTest extends TestCase
{
    private MailchimpRuntime $runtime;

    protected function setUp(): void
    {
        $this->runtime = new MailchimpRuntime();
    }

    public function test_returns_green_synced_badge_when_mailchimp_id_set(): void
    {
        $customer = $this->createCustomerMock('abc123', null, null);

        $badge = $this->runtime->getSyncBadge($customer);

        $this->assertSame('synced', $badge['label']);
        $this->assertSame('green', $badge['color']);
    }

    public function test_returns_red_error_badge_when_error_is_set(): void
    {
        $customer = $this->createCustomerMock('abc123', null, 'Some error');

        $badge = $this->runtime->getSyncBadge($customer);

        $this->assertSame('error', $badge['label']);
        $this->assertSame('red', $badge['color']);
    }

    public function test_returns_grey_never_badge_when_never_synced(): void
    {
        $customer = $this->createCustomerMock(null, null, null);

        $badge = $this->runtime->getSyncBadge($customer);

        $this->assertSame('never', $badge['label']);
        $this->assertSame('grey', $badge['color']);
    }

    public function test_returns_member_url(): void
    {
        $url = $this->runtime->getMemberUrl('audience-id', 'mailchimp-id');

        $this->assertStringContainsString('mailchimp.com', $url);
        $this->assertStringContainsString('mailchimp-id', $url);
    }

    private function createCustomerMock(?string $mailchimpId, ?\DateTimeInterface $syncedAt, ?string $error): MailchimpAwareInterface
    {
        $customer = $this->createMock(MailchimpAwareInterface::class);
        $customer->method('getMailchimpId')->willReturn($mailchimpId);
        $customer->method('getMailchimpSyncedAt')->willReturn($syncedAt);
        $customer->method('getMailchimpError')->willReturn($error);

        return $customer;
    }
}
