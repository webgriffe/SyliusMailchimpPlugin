<?php

declare(strict_types=1);

namespace Tests\Webgriffe\SyliusMailchimpPlugin\Unit\Command;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Console\Tester\CommandTester;
use Tests\Webgriffe\SyliusMailchimpPlugin\Entity\Order\Order;
use Webgriffe\SyliusMailchimpPlugin\Command\SyncCartsCommand;
use Webgriffe\SyliusMailchimpPlugin\Enqueuer\CartEnqueuerInterface;
use Webgriffe\SyliusMailchimpPlugin\Repository\MailchimpOrderRepositoryInterface;

final class SyncCartsCommandTest extends TestCase
{
    private MockObject&MailchimpOrderRepositoryInterface $orderRepository;

    private MockObject&CartEnqueuerInterface $cartEnqueuer;

    private SyncCartsCommand $command;

    protected function setUp(): void
    {
        $this->orderRepository = $this->createMock(MailchimpOrderRepositoryInterface::class);
        $this->cartEnqueuer = $this->createMock(CartEnqueuerInterface::class);
        $this->command = new SyncCartsCommand(
            $this->orderRepository,
            $this->cartEnqueuer,
            false,
            new NullLogger(),
        );
    }

    public function test_syncs_all_carts_when_no_options(): void
    {
        $order = new Order();
        $this->orderRepository->expects(self::once())->method('findMailchimpCartSyncNeeded')->willReturn([$order]);

        $this->cartEnqueuer->expects(self::once())->method('enqueue')->with($order);

        $tester = new CommandTester($this->command);
        $tester->execute([]);

        self::assertSame(0, $tester->getStatusCode());
    }

    public function test_syncs_carts_updated_since_when_updated_last_days_option_provided(): void
    {
        $order = new Order();
        $this->orderRepository->expects(self::once())->method('findMailchimpCartSyncNeededUpdatedSince')
            ->willReturn([$order]);

        $this->cartEnqueuer->expects(self::once())->method('enqueue')->with($order);

        $tester = new CommandTester($this->command);
        $tester->execute(['--updated-last-days' => '7']);

        self::assertSame(0, $tester->getStatusCode());
    }

    public function test_skips_non_order_subjects(): void
    {
        $this->orderRepository->method('findMailchimpCartSyncNeeded')->willReturn([new \stdClass()]);

        $this->cartEnqueuer->expects(self::never())->method('enqueue');

        $tester = new CommandTester($this->command);
        $tester->execute([]);

        self::assertSame(0, $tester->getStatusCode());
    }
}
