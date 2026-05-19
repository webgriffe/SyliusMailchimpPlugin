<?php

declare(strict_types=1);

namespace Tests\Webgriffe\SyliusMailchimpPlugin\Unit\Command;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Sylius\Component\Core\Model\CustomerInterface;
use Symfony\Component\Console\Tester\CommandTester;
use Tests\Webgriffe\SyliusMailchimpPlugin\Entity\Customer\Customer;
use Webgriffe\SyliusMailchimpPlugin\Command\SyncMembersCommand;
use Webgriffe\SyliusMailchimpPlugin\Enqueuer\MemberEnqueuerInterface;
use Webgriffe\SyliusMailchimpPlugin\Repository\MailchimpCustomerRepositoryInterface;

final class SyncMembersCommandTest extends TestCase
{
    private MockObject&MailchimpCustomerRepositoryInterface $customerRepository;

    private MockObject&MemberEnqueuerInterface $memberEnqueuer;

    private SyncMembersCommand $command;

    protected function setUp(): void
    {
        $this->customerRepository = $this->createMock(MailchimpCustomerRepositoryInterface::class);
        $this->memberEnqueuer = $this->createMock(MemberEnqueuerInterface::class);
        $this->command = new SyncMembersCommand(
            $this->customerRepository,
            $this->memberEnqueuer,
            new NullLogger(),
            false,
        );
    }

    public function test_syncs_all_when_no_options(): void
    {
        $customer = new Customer();
        $this->customerRepository->expects($this->once())->method('findMailchimpSyncNeeded')
            ->willReturn([$customer]);

        $this->memberEnqueuer->expects($this->once())->method('enqueue')->with($customer);

        $tester = new CommandTester($this->command);
        $tester->execute([]);

        $this->assertSame(0, $tester->getStatusCode());
    }

    public function test_syncs_by_ids_when_customer_ids_option_provided(): void
    {
        $customer = new Customer();
        $this->customerRepository->expects($this->once())->method('findMailchimpByIds')
            ->with([1, 2])
            ->willReturn([$customer]);

        $this->memberEnqueuer->expects($this->once())->method('enqueue');

        $tester = new CommandTester($this->command);
        $tester->execute(['--customer-ids' => ['1', '2']]);

        $this->assertSame(0, $tester->getStatusCode());
    }

    public function test_syncs_since_date_when_updated_last_days_option_provided(): void
    {
        $customer = new Customer();
        $this->customerRepository->expects($this->once())->method('findMailchimpSyncNeededSince')
            ->willReturn([$customer]);

        $this->memberEnqueuer->expects($this->once())->method('enqueue');

        $tester = new CommandTester($this->command);
        $tester->execute(['--updated-last-days' => '7']);

        $this->assertSame(0, $tester->getStatusCode());
    }

    public function test_skips_non_customer_objects(): void
    {
        $this->customerRepository->method('findMailchimpSyncNeeded')
            ->willReturn([new \stdClass()]);

        $this->memberEnqueuer->expects($this->never())->method('enqueue');

        $tester = new CommandTester($this->command);
        $tester->execute([]);

        $this->assertSame(0, $tester->getStatusCode());
    }

    public function test_skips_non_mailchimp_aware_customers(): void
    {
        $customer = $this->createMock(CustomerInterface::class);
        $this->customerRepository->method('findMailchimpSyncNeeded')
            ->willReturn([$customer]);

        $this->memberEnqueuer->expects($this->never())->method('enqueue');

        $tester = new CommandTester($this->command);
        $tester->execute([]);

        $this->assertSame(0, $tester->getStatusCode());
    }
}
