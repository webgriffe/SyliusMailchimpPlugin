<?php

declare(strict_types=1);

namespace Tests\Webgriffe\SyliusMailchimpPlugin\Unit\Command;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Sylius\Component\Core\Model\OrderInterface;
use Symfony\Component\Console\Tester\CommandTester;
use Webgriffe\SyliusMailchimpPlugin\Command\SyncOrdersCommand;
use Webgriffe\SyliusMailchimpPlugin\Enqueuer\OrderEnqueuerInterface;
use Webgriffe\SyliusMailchimpPlugin\Repository\MailchimpOrderRepositoryInterface;

final class SyncOrdersCommandTest extends TestCase
{
    private MockObject&MailchimpOrderRepositoryInterface $orderRepository;

    private MockObject&OrderEnqueuerInterface $orderEnqueuer;

    private SyncOrdersCommand $command;

    protected function setUp(): void
    {
        $this->orderRepository = $this->createMock(MailchimpOrderRepositoryInterface::class);
        $this->orderEnqueuer = $this->createMock(OrderEnqueuerInterface::class);
        $this->command = new SyncOrdersCommand(
            $this->orderRepository,
            $this->orderEnqueuer,
            false,
            new NullLogger(),
        );
    }

    public function test_syncs_all_orders_when_no_options(): void
    {
        $order = $this->createMock(OrderInterface::class);
        $this->orderRepository->expects(self::once())->method('findAll')->willReturn([$order]);

        $this->orderEnqueuer->expects(self::once())->method('enqueue')->with($order);

        $tester = new CommandTester($this->command);
        $tester->execute([]);

        self::assertSame(0, $tester->getStatusCode());
    }

    public function test_syncs_only_unsynced_orders_when_create_only_option_provided(): void
    {
        $order = $this->createMock(OrderInterface::class);
        $this->orderRepository->expects(self::once())->method('findMailchimpOrderSyncNeeded')->willReturn([$order]);

        $this->orderEnqueuer->expects(self::once())->method('enqueue')->with($order);

        $tester = new CommandTester($this->command);
        $tester->execute(['--create-only' => true]);

        self::assertSame(0, $tester->getStatusCode());
    }

    public function test_syncs_orders_updated_since_when_updated_last_days_option_provided(): void
    {
        $order = $this->createMock(OrderInterface::class);
        $this->orderRepository->expects(self::once())->method('findMailchimpOrderSyncNeededUpdatedSince')
            ->willReturn([$order]);

        $this->orderEnqueuer->expects(self::once())->method('enqueue')->with($order);

        $tester = new CommandTester($this->command);
        $tester->execute(['--updated-last-days' => '7', '--create-only' => true]);

        self::assertSame(0, $tester->getStatusCode());
    }

    public function test_skips_non_order_subjects(): void
    {
        $this->orderRepository->method('findAll')->willReturn([new \stdClass()]);

        $this->orderEnqueuer->expects(self::never())->method('enqueue');

        $tester = new CommandTester($this->command);
        $tester->execute([]);

        self::assertSame(0, $tester->getStatusCode());
    }
}
