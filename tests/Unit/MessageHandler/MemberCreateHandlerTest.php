<?php

declare(strict_types=1);

namespace Tests\Webgriffe\SyliusMailchimpPlugin\Unit\MessageHandler;

use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Sylius\Component\Core\Model\CustomerInterface;
use Sylius\Component\Core\Repository\CustomerRepositoryInterface;
use Tests\Webgriffe\SyliusMailchimpPlugin\Entity\Customer\Customer;
use Webgriffe\SyliusMailchimpPlugin\Client\Exception\ComplianceStateException;
use Webgriffe\SyliusMailchimpPlugin\Client\MailchimpClientInterface;
use Webgriffe\SyliusMailchimpPlugin\Mapper\MemberMapperInterface;
use Webgriffe\SyliusMailchimpPlugin\Message\Member\MemberCreate;
use Webgriffe\SyliusMailchimpPlugin\MessageHandler\Member\MemberCreateHandler;
use Webgriffe\SyliusMailchimpPlugin\ValueObject\Member;
use Webgriffe\SyliusMailchimpPlugin\ValueObject\MergeFields;

final class MemberCreateHandlerTest extends TestCase
{
    private MockObject&CustomerRepositoryInterface $customerRepository;

    private MockObject&MemberMapperInterface $memberMapper;

    private MockObject&MailchimpClientInterface $mailchimpClient;

    private MockObject&EntityManagerInterface $entityManager;

    private MemberCreateHandler $handler;

    protected function setUp(): void
    {
        $this->customerRepository = $this->createMock(CustomerRepositoryInterface::class);
        $this->memberMapper = $this->createMock(MemberMapperInterface::class);
        $this->mailchimpClient = $this->createMock(MailchimpClientInterface::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);

        $this->handler = new MemberCreateHandler(
            $this->customerRepository,
            $this->memberMapper,
            $this->mailchimpClient,
            $this->entityManager,
            new NullLogger(),
        );
    }

    public function test_skips_when_customer_not_found(): void
    {
        $this->customerRepository->method('find')->willReturn(null);
        $this->memberMapper->expects($this->never())->method('map');

        ($this->handler)(new MemberCreate(999, 'list-id'));
    }

    public function test_skips_when_customer_does_not_implement_mailchimp_aware(): void
    {
        $customer = $this->createMock(CustomerInterface::class);
        $this->customerRepository->method('find')->willReturn($customer);
        $this->memberMapper->expects($this->never())->method('map');

        ($this->handler)(new MemberCreate(1, 'list-id'));
    }

    public function test_skips_when_customer_not_subscribed_to_newsletter(): void
    {
        $customer = new Customer();
        $this->customerRepository->method('find')->willReturn($customer);
        $this->memberMapper->expects($this->never())->method('map');
        $this->mailchimpClient->expects($this->never())->method('upsertMember');

        ($this->handler)(new MemberCreate(1, 'list-id'));
    }

    public function test_upserts_member_and_updates_customer(): void
    {
        $customer = new Customer();
        $customer->setSubscribedToNewsletter(true);
        $this->customerRepository->method('find')->willReturn($customer);

        $member = new Member('test@example.com', 'subscribed', new MergeFields('', ''));
        $this->memberMapper->method('map')->willReturn($member);
        $this->mailchimpClient->method('upsertMember')->willReturn('newmailchimpid');
        $this->entityManager->expects($this->once())->method('flush');

        ($this->handler)(new MemberCreate(1, 'list-id'));

        $this->assertSame('newmailchimpid', $customer->getMailchimpId());
        $this->assertNotNull($customer->getMailchimpSyncedAt());
        $this->assertNull($customer->getMailchimpError());
    }

    public function test_stores_error_on_compliance_state_exception(): void
    {
        $customer = new Customer();
        $customer->setSubscribedToNewsletter(true);
        $this->customerRepository->method('find')->willReturn($customer);

        $member = new Member('test@example.com', 'subscribed', new MergeFields('', ''));
        $this->memberMapper->method('map')->willReturn($member);
        $this->mailchimpClient->method('upsertMember')
            ->willThrowException(ComplianceStateException::forEmail('test@example.com'));
        $this->entityManager->expects($this->once())->method('flush');

        ($this->handler)(new MemberCreate(1, 'list-id'));

        $this->assertStringContainsString('compliance', (string) $customer->getMailchimpError());
    }
}
