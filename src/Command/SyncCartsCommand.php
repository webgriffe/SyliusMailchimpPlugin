<?php

declare(strict_types=1);

namespace Webgriffe\SyliusMailchimpPlugin\Command;

use Sylius\Component\Core\Model\OrderInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Command\LockableTrait;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Webgriffe\SyliusMailchimpPlugin\Enqueuer\CartEnqueuerInterface;
use Webgriffe\SyliusMailchimpPlugin\Repository\MailchimpOrderRepositoryInterface;

#[AsCommand(
    name: 'webgriffe:sylius-mailchimp:sync-carts',
    description: 'Synchronizes open carts with Mailchimp e-commerce stores.',
)]
final class SyncCartsCommand extends Command
{
    use LockableTrait;

    public function __construct(
        private readonly MailchimpOrderRepositoryInterface $orderRepository,
        private readonly CartEnqueuerInterface $cartEnqueuer,
        private readonly bool $commandLockEnable,
    ) {
        parent::__construct();
    }

    #[\Override]
    protected function configure(): void
    {
        $this->addOption(
            'updated-last-days',
            null,
            InputOption::VALUE_REQUIRED,
            'Only sync carts updated in the last N days.',
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

        $orders = $this->resolveOrders($input);
        $total = count($orders);
        $io->writeln(sprintf('[Mailchimp] Syncing %d cart(s)…', $total));

        $enqueued = 0;
        $skipped = 0;

        foreach ($orders as $order) {
            if (!$order instanceof OrderInterface) {
                ++$skipped;

                continue;
            }

            $this->cartEnqueuer->enqueue($order);
            ++$enqueued;
        }

        $io->success(sprintf('Enqueued %d, skipped %d out of %d cart(s).', $enqueued, $skipped, $total));

        $this->release();

        return Command::SUCCESS;
    }

    /** @return object[] */
    private function resolveOrders(InputInterface $input): array
    {
        $updatedLastDays = $input->getOption('updated-last-days');

        if ($updatedLastDays !== null) {
            /** @phpstan-ignore-next-line */
            $since = new \DateTimeImmutable(sprintf('-%d days', (int) $updatedLastDays));

            return $this->orderRepository->findMailchimpCartSyncNeededUpdatedSince($since);
        }

        return $this->orderRepository->findMailchimpCartSyncNeeded();
    }
}
