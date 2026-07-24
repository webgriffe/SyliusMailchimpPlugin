<?php

declare(strict_types=1);

namespace Tests\Webgriffe\SyliusMailchimpPlugin\Unit\Twig;

use PHPUnit\Framework\TestCase;
use Webgriffe\SyliusMailchimpPlugin\Model\MailchimpAwareInterface;
use Webgriffe\SyliusMailchimpPlugin\Repository\MailchimpCustomerRepositoryInterface;
use Webgriffe\SyliusMailchimpPlugin\Repository\MailchimpOrderRepositoryInterface;
use Webgriffe\SyliusMailchimpPlugin\Twig\MailchimpRuntime;

final class MailchimpRuntimeTest extends TestCase
{
    private MailchimpRuntime $runtime;

    private MailchimpCustomerRepositoryInterface $customerRepository;

    private MailchimpOrderRepositoryInterface $orderRepository;

    protected function setUp(): void
    {
        $this->customerRepository = $this->createMock(MailchimpCustomerRepositoryInterface::class);
        $this->orderRepository = $this->createMock(MailchimpOrderRepositoryInterface::class);
        $this->runtime = new MailchimpRuntime($this->customerRepository, $this->orderRepository, 'abc123-us6');
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
        $url = $this->runtime->getMemberUrl('mailchimp-id');

        $this->assertSame('https://us6.admin.mailchimp.com/audience/contact-profile?contact_id=mailchimp-id', $url);
    }

    public function test_returns_members_synced_count(): void
    {
        $this->customerRepository->method('countMailchimpSyncedMembers')->willReturn(42);

        $this->assertSame(42, $this->runtime->getMembersSyncedCount());
    }

    public function test_returns_members_error_count(): void
    {
        $this->customerRepository->method('countMailchimpMembersWithError')->willReturn(5);

        $this->assertSame(5, $this->runtime->getMembersErrorCount());
    }

    public function test_returns_members_never_synced_count(): void
    {
        $this->customerRepository->method('countMailchimpNeverSyncedMembers')->willReturn(10);

        $this->assertSame(10, $this->runtime->getMembersNeverSyncedCount());
    }

    public function test_returns_pending_carts_count(): void
    {
        $this->orderRepository->method('countMailchimpPendingCarts')->willReturn(3);

        $this->assertSame(3, $this->runtime->getPendingCartsCount());
    }

    public function test_returns_pending_orders_count(): void
    {
        $this->orderRepository->method('countMailchimpPendingOrders')->willReturn(7);

        $this->assertSame(7, $this->runtime->getPendingOrdersCount());
    }

    public function test_parses_mailchimp_api_error_with_title_and_detail(): void
    {
        $message = 'Mailchimp API error 400: {"type":"about:blank","title":"Invalid Resource","detail":"The resource submitted could not be validated.","status":400}';

        $error = $this->runtime->parseError($message);

        $this->assertSame(400, $error->statusCode);
        $this->assertSame('Invalid Resource', $error->title);
        $this->assertSame('The resource submitted could not be validated.', $error->detail);
        $this->assertSame($message, $error->raw);
    }

    public function test_parses_mailchimp_api_error_with_non_json_body(): void
    {
        $message = 'Mailchimp API error 500: Internal Server Error';

        $error = $this->runtime->parseError($message);

        $this->assertSame(500, $error->statusCode);
        $this->assertNull($error->title);
        $this->assertNull($error->detail);
        $this->assertSame('Internal Server Error', $error->prettyRaw);
    }

    public function test_parses_unrecognized_error_format_as_raw_only(): void
    {
        $message = 'Member "foo@example.com" is in compliance state and cannot be subscribed.';

        $error = $this->runtime->parseError($message);

        $this->assertNull($error->statusCode);
        $this->assertNull($error->title);
        $this->assertNull($error->detail);
        $this->assertSame($message, $error->raw);
        $this->assertSame($message, $error->prettyRaw);
    }

    public function test_parses_null_error_as_empty(): void
    {
        $error = $this->runtime->parseError(null);

        $this->assertNull($error->statusCode);
        $this->assertSame('', $error->raw);
        $this->assertSame('', $error->prettyRaw);
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
