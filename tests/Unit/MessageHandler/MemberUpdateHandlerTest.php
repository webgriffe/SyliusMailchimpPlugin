<?php

declare(strict_types=1);

namespace Tests\Webgriffe\SyliusMailchimpPlugin\Unit\MessageHandler;

use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Sylius\Component\Core\Repository\CustomerRepositoryInterface;
use Tests\Webgriffe\SyliusMailchimpPlugin\Entity\Customer\Customer;
use Webgriffe\SyliusMailchimpPlugin\Client\MailchimpClientInterface;
use Webgriffe\SyliusMailchimpPlugin\Mapper\MemberMapperInterface;
use Webgriffe\SyliusMailchimpPlugin\Message\Member\MemberUpdate;
use Webgriffe\SyliusMailchimpPlugin\MessageHandler\Member\MemberUpdateHandler;
use Webgriffe\SyliusMailchimpPlugin\ValueObject\Member;
use Webgriffe\SyliusMailchimpPlugin\ValueObject\MergeFields;

final class MemberUpdateHandlerTest extends TestCase
{
    private MockObject&CustomerRepositoryInterface $customerRepository;

    private MockObject&MemberMapperInterface $memberMapper;

    private MockObject&MailchimpClientInterface $mailchimpClient;

    private MockObject&EntityManagerInterface $entityManager;

    private MemberUpdateHandler $handler;

    protected function setUp(): void
    {
        $this->customerRepository = $this->createMock(CustomerRepositoryInterface::class);
        $this->memberMapper = $this->createMock(MemberMapperInterface::class);
        $this->mailchimpClient = $this->createMock(MailchimpClientInterface::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);

        $this->handler = new MemberUpdateHandler(
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

        ($this->handler)(new MemberUpdate(999, 'list-id'));
    }

    public function test_skips_when_customer_not_subscribed_to_newsletter(): void
    {
        $customer = new Customer();
        $this->customerRepository->method('find')->willReturn($customer);
        $this->memberMapper->expects($this->never())->method('map');
        $this->mailchimpClient->expects($this->never())->method('upsertMember');

        ($this->handler)(new MemberUpdate(1, 'list-id'));
    }

    public function test_upserts_member_and_updates_customer(): void
    {
        $customer = new Customer();
        $customer->setSubscribedToNewsletter(true);
        $this->customerRepository->method('find')->willReturn($customer);

        $member = new Member('test@example.com', 'subscribed', new MergeFields('', ''));
        $this->memberMapper->method('map')->willReturn($member);
        $this->mailchimpClient->method('upsertMember')->willReturn('mailchimpid');
        $this->entityManager->expects($this->once())->method('flush');

        ($this->handler)(new MemberUpdate(1, 'list-id'));

        $this->assertSame('mailchimpid', $customer->getMailchimpId());
        $this->assertNotNull($customer->getMailchimpSyncedAt());
    }
}
