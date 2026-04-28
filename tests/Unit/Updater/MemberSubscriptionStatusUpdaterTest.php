<?php

declare(strict_types=1);

namespace Tests\Webgriffe\SyliusMailchimpPlugin\Unit\Updater;

use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Sylius\Component\Core\Model\CustomerInterface;
use Sylius\Component\Core\Repository\CustomerRepositoryInterface;
use Webgriffe\SyliusMailchimpPlugin\Model\MailchimpAwareInterface;
use Webgriffe\SyliusMailchimpPlugin\Updater\MemberSubscriptionStatusUpdater;

interface TestMailchimpCustomerInterface extends CustomerInterface, MailchimpAwareInterface
{
}

final class MemberSubscriptionStatusUpdaterTest extends TestCase
{
    private MockObject&CustomerRepositoryInterface $customerRepository;

    private MockObject&EntityManagerInterface $entityManager;

    private MemberSubscriptionStatusUpdater $updater;

    protected function setUp(): void
    {
        $this->customerRepository = $this->createMock(CustomerRepositoryInterface::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->updater = new MemberSubscriptionStatusUpdater(
            $this->customerRepository,
            $this->entityManager,
            new NullLogger(),
        );
    }

    public function test_skips_when_customer_not_found(): void
    {
        $this->customerRepository->method('findOneBy')->willReturn(null);
        $this->entityManager->expects($this->never())->method('flush');

        $this->updater->update('subscribe', 'user@example.com', 'list-abc');
    }

    public function test_skips_when_customer_not_mailchimp_aware(): void
    {
        $customer = $this->createMock(CustomerInterface::class);
        $this->customerRepository->method('findOneBy')->willReturn($customer);
        $this->entityManager->expects($this->never())->method('flush');

        $this->updater->update('subscribe', 'user@example.com', 'list-abc');
    }

    public function test_sets_mailchimp_id_on_subscribe(): void
    {
        $customer = $this->createMock(TestMailchimpCustomerInterface::class);
        $customer->method('getEmail')->willReturn('user@example.com');
        $this->customerRepository->method('findOneBy')->willReturn($customer);

        $expectedHash = md5('user@example.com');
        $customer->expects($this->once())->method('setMailchimpId')->with($expectedHash);
        $customer->expects($this->once())->method('setMailchimpSyncedAt');
        $customer->expects($this->once())->method('setMailchimpError')->with(null);
        $this->entityManager->expects($this->once())->method('flush');

        $this->updater->update('subscribe', 'user@example.com', 'list-abc');
    }

    public function test_clears_mailchimp_id_on_unsubscribe(): void
    {
        $customer = $this->createMock(TestMailchimpCustomerInterface::class);
        $this->customerRepository->method('findOneBy')->willReturn($customer);

        $customer->expects($this->once())->method('setMailchimpId')->with(null);
        $customer->expects($this->once())->method('setMailchimpSyncedAt');
        $customer->expects($this->once())->method('setMailchimpError')->with(null);
        $this->entityManager->expects($this->once())->method('flush');

        $this->updater->update('unsubscribe', 'user@example.com', 'list-abc');
    }

    public function test_clears_mailchimp_id_on_cleaned(): void
    {
        $customer = $this->createMock(TestMailchimpCustomerInterface::class);
        $this->customerRepository->method('findOneBy')->willReturn($customer);

        $customer->expects($this->once())->method('setMailchimpId')->with(null);
        $this->entityManager->expects($this->once())->method('flush');

        $this->updater->update('cleaned', 'user@example.com', 'list-abc');
    }

    public function test_updates_synced_at_on_profile_update(): void
    {
        $customer = $this->createMock(TestMailchimpCustomerInterface::class);
        $this->customerRepository->method('findOneBy')->willReturn($customer);

        $customer->expects($this->never())->method('setMailchimpId');
        $customer->expects($this->once())->method('setMailchimpSyncedAt');
        $customer->expects($this->once())->method('setMailchimpError')->with(null);
        $this->entityManager->expects($this->once())->method('flush');

        $this->updater->update('profile', 'user@example.com', 'list-abc');
    }
}
