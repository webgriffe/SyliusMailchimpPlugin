<?php

declare(strict_types=1);

namespace Tests\Webgriffe\SyliusMailchimpPlugin\Unit\Updater;

use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Sylius\Component\Core\Model\CustomerInterface;
use Sylius\Component\Core\Repository\CustomerRepositoryInterface;
use Tests\Webgriffe\SyliusMailchimpPlugin\Entity\Customer\Customer;
use Webgriffe\SyliusMailchimpPlugin\Updater\MemberSubscriptionStatusUpdater;

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
        $customer = new Customer();
        $customer->setEmail('user@example.com');
        $this->customerRepository->method('findOneBy')->willReturn($customer);
        $this->entityManager->expects($this->once())->method('flush');

        $this->updater->update('subscribe', 'user@example.com', 'list-abc');

        $expectedHash = md5('user@example.com');
        $this->assertSame($expectedHash, $customer->getMailchimpId());
        $this->assertNotNull($customer->getMailchimpSyncedAt());
        $this->assertNull($customer->getMailchimpError());
    }

    public function test_clears_mailchimp_id_on_unsubscribe(): void
    {
        $customer = new Customer();
        $customer->setMailchimpId('some-id');
        $this->customerRepository->method('findOneBy')->willReturn($customer);
        $this->entityManager->expects($this->once())->method('flush');

        $this->updater->update('unsubscribe', 'user@example.com', 'list-abc');

        $this->assertNull($customer->getMailchimpId());
        $this->assertNotNull($customer->getMailchimpSyncedAt());
        $this->assertNull($customer->getMailchimpError());
    }

    public function test_clears_mailchimp_id_on_cleaned(): void
    {
        $customer = new Customer();
        $customer->setMailchimpId('some-id');
        $this->customerRepository->method('findOneBy')->willReturn($customer);
        $this->entityManager->expects($this->once())->method('flush');

        $this->updater->update('cleaned', 'user@example.com', 'list-abc');

        $this->assertNull($customer->getMailchimpId());
    }

    public function test_updates_synced_at_on_profile_update(): void
    {
        $customer = new Customer();
        $customer->setMailchimpId('existing-id');
        $this->customerRepository->method('findOneBy')->willReturn($customer);
        $this->entityManager->expects($this->once())->method('flush');

        $this->updater->update('profile', 'user@example.com', 'list-abc');

        $this->assertSame('existing-id', $customer->getMailchimpId());
        $this->assertNotNull($customer->getMailchimpSyncedAt());
        $this->assertNull($customer->getMailchimpError());
    }
}
