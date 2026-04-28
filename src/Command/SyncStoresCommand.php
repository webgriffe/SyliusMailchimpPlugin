<?php

declare(strict_types=1);

namespace Webgriffe\SyliusMailchimpPlugin\Command;

use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Resource\Repository\RepositoryInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Command\LockableTrait;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Webgriffe\SyliusMailchimpPlugin\Enqueuer\StoreEnqueuerInterface;
use Webgriffe\SyliusMailchimpPlugin\Model\ChannelMailchimpAwareInterface;

#[AsCommand(
    name: 'webgriffe:sylius-mailchimp:sync-stores',
    description: 'Synchronizes Mailchimp-aware channels as Mailchimp e-commerce stores.',
)]
final class SyncStoresCommand extends Command
{
    use LockableTrait;

    public function __construct(
        private readonly RepositoryInterface $channelRepository,
        private readonly StoreEnqueuerInterface $storeEnqueuer,
        private readonly bool $commandLockEnable,
    ) {
        parent::__construct();
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if ($this->commandLockEnable && !$this->lock()) {
            $output->writeln('The command is already running in another process.');

            return Command::SUCCESS;
        }

        $io = new SymfonyStyle($input, $output);

        $channels = $this->channelRepository->findAll();
        $enqueued = 0;
        $skipped = 0;

        foreach ($channels as $channel) {
            if (!$channel instanceof ChannelInterface || !$channel instanceof ChannelMailchimpAwareInterface) {
                ++$skipped;

                continue;
            }

            $this->storeEnqueuer->enqueue($channel);
            ++$enqueued;
        }

        $io->success(sprintf('Enqueued %d, skipped %d out of %d channel(s).', $enqueued, $skipped, count($channels)));

        $this->release();

        return Command::SUCCESS;
    }
}
