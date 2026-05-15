<?php

declare(strict_types=1);

namespace Tests\Webgriffe\SyliusMailchimpPlugin\Unit\Enqueuer;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Sylius\Component\Core\Model\CustomerInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Webgriffe\SyliusMailchimpPlugin\Client\MailchimpClientInterface;
use Webgriffe\SyliusMailchimpPlugin\Enqueuer\MemberEnqueuer;
use Webgriffe\SyliusMailchimpPlugin\Exception\AudienceNotFoundException;
use Webgriffe\SyliusMailchimpPlugin\Message\Member\MemberCreate;
use Webgriffe\SyliusMailchimpPlugin\Message\Member\MemberRemove;
use Webgriffe\SyliusMailchimpPlugin\Message\Member\MemberUpdate;
use Webgriffe\SyliusMailchimpPlugin\Model\MailchimpAwareInterface;
use Webgriffe\SyliusMailchimpPlugin\Provider\AudienceContextInterface;
use Webgriffe\SyliusMailchimpPlugin\ValueObject\Member;
use Webgriffe\SyliusMailchimpPlugin\ValueObject\MergeFields;

interface TestMailchimpCustomerInterface extends CustomerInterface, MailchimpAwareInterface
{
}

final class MemberEnqueuerTest extends TestCase
{
    private MockObject&MessageBusInterface $messageBus;

    private MockObject&AudienceContextInterface $audienceContext;

    private MockObject&MailchimpClientInterface $mailchimpClient;

    private MemberEnqueuer $enqueuer;

    protected function setUp(): void
    {
        $this->messageBus = $this->createMock(MessageBusInterface::class);
        $this->audienceContext = $this->createMock(AudienceContextInterface::class);
        $this->mailchimpClient = $this->createMock(MailchimpClientInterface::class);

        $this->enqueuer = new MemberEnqueuer(
            $this->messageBus,
            $this->audienceContext,
            $this->mailchimpClient,
            new NullLogger(),
        );
    }

    public function test_skips_when_customer_not_subscribed_to_newsletter(): void
    {
        $customer = $this->createMock(TestMailchimpCustomerInterface::class);
        $customer->method('isSubscribedToNewsletter')->willReturn(false);
        $this->messageBus->expects($this->never())->method('dispatch');

        $this->enqueuer->enqueue($customer);
    }

    public function test_skips_when_customer_is_not_mailchimp_aware(): void
    {
        $customer = $this->createMock(CustomerInterface::class);
        $customer->method('isSubscribedToNewsletter')->willReturn(true);
        $this->messageBus->expects($this->never())->method('dispatch');

        $this->enqueuer->enqueue($customer);
    }

    public function test_skips_when_customer_has_no_integer_id(): void
    {
        $customer = $this->createMock(TestMailchimpCustomerInterface::class);
        $customer->method('isSubscribedToNewsletter')->willReturn(true);
        $customer->method('getId')->willReturn(null);
        $this->messageBus->expects($this->never())->method('dispatch');

        $this->enqueuer->enqueue($customer);
    }

    public function test_skips_when_customer_has_no_email(): void
    {
        $customer = $this->createMock(TestMailchimpCustomerInterface::class);
        $customer->method('isSubscribedToNewsletter')->willReturn(true);
        $customer->method('getId')->willReturn(1);
        $customer->method('getEmail')->willReturn(null);
        $this->messageBus->expects($this->never())->method('dispatch');

        $this->enqueuer->enqueue($customer);
    }

    public function test_skips_when_audience_not_found(): void
    {
        $customer = $this->buildCustomer(1, 'test@example.com', null);
        $this->audienceContext->method('getAudienceId')
            ->willThrowException(new AudienceNotFoundException('No audience'));
        $this->messageBus->expects($this->never())->method('dispatch');

        $this->enqueuer->enqueue($customer);
    }
    public function test_dispatches_member_create_when_no_mailchimp_id_and_no_remote_member(): void
    {
        $customer = $this->buildCustomer(1, 'test@example.com', null);
        $this->audienceContext->method('getAudienceId')->willReturn('list-abc');
        $this->mailchimpClient->method('getMember')->willReturn(null);

        $this->messageBus->expects($this->once())
            ->method('dispatch')
            ->with($this->isInstanceOf(MemberCreate::class))
            ->willReturn(new Envelope(new MemberCreate(1, 'list-abc')));

        $this->enqueuer->enqueue($customer);
    }

    public function test_dispatches_member_update_when_no_mailchimp_id_but_remote_member_exists(): void
    {
        $customer = $this->buildCustomer(1, 'test@example.com', null);
        $this->audienceContext->method('getAudienceId')->willReturn('list-abc');
        $remoteMember = new Member('test@example.com', 'subscribed', new MergeFields('', ''));
        $this->mailchimpClient->method('getMember')->willReturn($remoteMember);

        $this->messageBus->expects($this->once())
            ->method('dispatch')
            ->with($this->isInstanceOf(MemberUpdate::class))
            ->willReturn(new Envelope(new MemberUpdate(1, 'list-abc')));

        $this->enqueuer->enqueue($customer);
    }

    public function test_dispatches_member_update_when_mailchimp_id_already_set(): void
    {
        $customer = $this->buildCustomer(1, 'test@example.com', 'existing-mailchimp-id');
        $this->audienceContext->method('getAudienceId')->willReturn('list-abc');

        $this->messageBus->expects($this->once())
            ->method('dispatch')
            ->with($this->isInstanceOf(MemberUpdate::class))
            ->willReturn(new Envelope(new MemberUpdate(1, 'list-abc')));

        $this->enqueuer->enqueue($customer);
    }

    public function test_enqueue_removal_dispatches_member_remove(): void
    {
        $expectedHash = md5(strtolower('test@example.com'));

        $this->messageBus->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(
                static fn (MemberRemove $msg): bool => $msg->subscriberHash === $expectedHash
                    && $msg->listId === 'list-abc'
                    && $msg->customerId === 1,
            ))
            ->willReturn(new Envelope(new MemberRemove(1, 'list-abc', $expectedHash)));

        $this->enqueuer->enqueueRemoval(1, 'list-abc', 'test@example.com');
    }

    private function buildCustomer(int $id, string $email, ?string $mailchimpId): TestMailchimpCustomerInterface
    {
        $customer = $this->createMock(TestMailchimpCustomerInterface::class);
        $customer->method('getId')->willReturn($id);
        $customer->method('getEmail')->willReturn($email);
        $customer->method('getMailchimpId')->willReturn($mailchimpId);
        $customer->method('isSubscribedToNewsletter')->willReturn(true);

        return $customer;
    }
}
