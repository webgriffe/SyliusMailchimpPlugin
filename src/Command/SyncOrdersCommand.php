<?php

declare(strict_types=1);

namespace Webgriffe\SyliusMailchimpPlugin\Command;

use Psr\Log\LoggerInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Command\LockableTrait;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Webgriffe\SyliusMailchimpPlugin\Enqueuer\OrderEnqueuerInterface;
use Webgriffe\SyliusMailchimpPlugin\Repository\MailchimpOrderRepositoryInterface;

#[AsCommand(
    name: 'webgriffe:sylius-mailchimp:sync-orders',
    description: 'Synchronizes completed orders with Mailchimp e-commerce stores.',
)]
final class SyncOrdersCommand extends Command
{
    use LockableTrait;

    public function __construct(
        private readonly MailchimpOrderRepositoryInterface $orderRepository,
        private readonly OrderEnqueuerInterface $orderEnqueuer,
        private readonly bool $commandLockEnable,
        private readonly LoggerInterface $logger,
    ) {
        parent::__construct();
    }

    #[\Override]
    protected function configure(): void
    {
        $this
            ->addOption(
                'updated-last-days',
                null,
                InputOption::VALUE_REQUIRED,
                'Only sync orders updated in the last N days.',
            )
            ->addOption(
                'create-only',
                null,
                InputOption::VALUE_NONE,
                'Only enqueue orders that have never been synced (skip updates).',
            )
            ->addOption(
                'retry-failed',
                null,
                InputOption::VALUE_NONE,
                'Also retry records whose last sync failed permanently.',
            );
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if ($this->commandLockEnable && !$this->lock()) {
            $output->writeln('The command is already running in another process.');

            return Command::SUCCESS;
        }

        $io = new SymfonyStyle($input, $output);

        $createOnly = $input->getOption('create-only') === true;
        $orders = $this->resolveOrders($input, $createOnly, $input->getOption('retry-failed') === true);
        $total = count($orders);
        $io->writeln(sprintf('[Mailchimp] Syncing %d order(s)…', $total));

        $enqueued = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($orders as $order) {
            if (!$order instanceof OrderInterface) {
                ++$skipped;

                continue;
            }

            try {
                $this->orderEnqueuer->enqueue($order);
                ++$enqueued;
            } catch (\Throwable $e) {
                ++$failed;
                $this->logger->error('[Mailchimp] Failed to sync order #{id}: {message}', [
                    'id' => $order->getId(),
                    'message' => $e->getMessage(),
                ]);
            }
        }

        $io->success(sprintf('Enqueued %d, skipped %d, failed %d out of %d order(s).', $enqueued, $skipped, $failed, $total));

        $this->release();

        return Command::SUCCESS;
    }

    /** @return object[] */
    private function resolveOrders(InputInterface $input, bool $createOnly, bool $includeFailed): array
    {
        $updatedLastDays = $input->getOption('updated-last-days');
        $since = null;
        if ($updatedLastDays !== null) {
            /** @phpstan-ignore-next-line */
            $since = new \DateTimeImmutable(sprintf('-%d days', (int) $updatedLastDays));
        }

        if ($createOnly) {
            return $this->orderRepository->findMailchimpOrderSyncNeeded($since, $includeFailed);
        }

        if ($since !== null) {
            return $this->orderRepository->findMailchimpOrderSyncNeededUpdatedSince($since, $includeFailed);
        }

        return $this->orderRepository->findMailchimpOrderSyncable($includeFailed);
    }
}
