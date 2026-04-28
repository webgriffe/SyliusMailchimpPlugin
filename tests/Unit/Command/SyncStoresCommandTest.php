<?php

declare(strict_types=1);

namespace Tests\Webgriffe\SyliusMailchimpPlugin\Unit\Command;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Resource\Repository\RepositoryInterface;
use Symfony\Component\Console\Tester\CommandTester;
use Webgriffe\SyliusMailchimpPlugin\Command\SyncStoresCommand;
use Webgriffe\SyliusMailchimpPlugin\Enqueuer\StoreEnqueuerInterface;
use Webgriffe\SyliusMailchimpPlugin\Model\ChannelMailchimpAwareInterface;

interface TestMailchimpChannelForStoreTest extends ChannelInterface, ChannelMailchimpAwareInterface
{
}

final class SyncStoresCommandTest extends TestCase
{
    private MockObject&RepositoryInterface $channelRepository;

    private MockObject&StoreEnqueuerInterface $storeEnqueuer;

    private SyncStoresCommand $command;

    protected function setUp(): void
    {
        $this->channelRepository = $this->createMock(RepositoryInterface::class);
        $this->storeEnqueuer = $this->createMock(StoreEnqueuerInterface::class);
        $this->command = new SyncStoresCommand(
            $this->channelRepository,
            $this->storeEnqueuer,
            false,
        );
    }

    public function test_enqueues_mailchimp_aware_channels(): void
    {
        $channel = $this->createMock(TestMailchimpChannelForStoreTest::class);
        $this->channelRepository->method('findAll')->willReturn([$channel]);

        $this->storeEnqueuer->expects(self::once())->method('enqueue')->with($channel);

        $tester = new CommandTester($this->command);
        $tester->execute([]);

        self::assertSame(0, $tester->getStatusCode());
    }

    public function test_skips_non_mailchimp_aware_channels(): void
    {
        $channel = $this->createMock(ChannelInterface::class);
        $this->channelRepository->method('findAll')->willReturn([$channel]);

        $this->storeEnqueuer->expects(self::never())->method('enqueue');

        $tester = new CommandTester($this->command);
        $tester->execute([]);

        self::assertSame(0, $tester->getStatusCode());
    }
}
