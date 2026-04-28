<?php

declare(strict_types=1);

namespace Webgriffe\SyliusMailchimpPlugin\Command;

use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Command\LockableTrait;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Webgriffe\SyliusMailchimpPlugin\Enqueuer\ProductEnqueuerInterface;
use Webgriffe\SyliusMailchimpPlugin\Model\ChannelMailchimpAwareInterface;
use Webgriffe\SyliusMailchimpPlugin\Repository\MailchimpProductRepositoryInterface;

#[AsCommand(
    name: 'webgriffe:sylius-mailchimp:sync-products',
    description: 'Synchronizes products with Mailchimp e-commerce stores.',
)]
final class SyncProductsCommand extends Command
{
    use LockableTrait;

    public function __construct(
        private readonly MailchimpProductRepositoryInterface $productRepository,
        private readonly ProductEnqueuerInterface $productEnqueuer,
        private readonly bool $commandLockEnable,
    ) {
        parent::__construct();
    }

    #[\Override]
    protected function configure(): void
    {
        $this
            ->addOption(
                'channel-code',
                null,
                InputOption::VALUE_REQUIRED,
                'Only sync products belonging to this channel code.',
            )
            ->addOption(
                'updated-last-days',
                null,
                InputOption::VALUE_REQUIRED,
                'Only sync products updated in the last N days.',
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

        $products = $this->resolveProducts($input);
        $total = count($products);
        $io->writeln(sprintf('[Mailchimp] Syncing %d product(s)…', $total));

        $enqueued = 0;
        $skipped = 0;

        foreach ($products as $product) {
            if (!$product instanceof ProductInterface) {
                ++$skipped;

                continue;
            }

            $hasMailchimpChannel = false;
            foreach ($product->getChannels() as $channel) {
                if ($channel instanceof ChannelInterface && $channel instanceof ChannelMailchimpAwareInterface) {
                    $hasMailchimpChannel = true;

                    break;
                }
            }

            if (!$hasMailchimpChannel) {
                ++$skipped;

                continue;
            }

            $this->productEnqueuer->enqueue($product, isNew: false);
            ++$enqueued;
        }

        $io->success(sprintf('Enqueued %d, skipped %d out of %d product(s).', $enqueued, $skipped, $total));

        $this->release();

        return Command::SUCCESS;
    }

    /** @return object[] */
    private function resolveProducts(InputInterface $input): array
    {
        /** @phpstan-ignore-next-line */
        $channelCode = $input->getOption('channel-code');
        /** @phpstan-ignore-next-line */
        $updatedLastDays = $input->getOption('updated-last-days');

        if ($channelCode !== null) {
            if ($updatedLastDays !== null) {
                /** @phpstan-ignore-next-line */
                $since = new \DateTimeImmutable(sprintf('-%d days', (int) $updatedLastDays));

                /** @phpstan-ignore-next-line */
                return $this->productRepository->findMailchimpSyncableByChannelUpdatedSince($channelCode, $since);
            }

            /** @phpstan-ignore-next-line */
            return $this->productRepository->findMailchimpSyncableByChannel($channelCode);
        }

        if ($updatedLastDays !== null) {
            /** @phpstan-ignore-next-line */
            $since = new \DateTimeImmutable(sprintf('-%d days', (int) $updatedLastDays));

            return $this->productRepository->findMailchimpSyncableUpdatedSince($since);
        }

        return $this->productRepository->findAll();
    }
}
