<?php

declare(strict_types=1);

namespace Webgriffe\SyliusMailchimpPlugin\Command;

use Psr\Log\LoggerInterface;
use Sylius\Component\Core\Model\CustomerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Command\LockableTrait;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Webgriffe\SyliusMailchimpPlugin\Enqueuer\MemberEnqueuerInterface;
use Webgriffe\SyliusMailchimpPlugin\Exception\AudienceNotFoundException;
use Webgriffe\SyliusMailchimpPlugin\Model\MailchimpAwareInterface;
use Webgriffe\SyliusMailchimpPlugin\Repository\MailchimpCustomerRepositoryInterface;

#[AsCommand(
    name: 'webgriffe:sylius-mailchimp:sync-members',
    description: 'Synchronizes newsletter members with Mailchimp.',
)]
final class SyncMembersCommand extends Command
{
    use LockableTrait;

    public function __construct(
        private readonly MailchimpCustomerRepositoryInterface $customerRepository,
        private readonly MemberEnqueuerInterface $memberEnqueuer,
        private readonly LoggerInterface $logger,
        private readonly bool $commandLockEnable,
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
                'Only sync customers updated in the last N days.',
            )
            ->addOption(
                'customer-ids',
                null,
                InputOption::VALUE_IS_ARRAY | InputOption::VALUE_REQUIRED,
                'Only sync customers with these IDs.',
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

        $customers = $this->resolveCustomers($input);
        $total = count($customers);
        $io->writeln(sprintf('[Mailchimp] Syncing %d customer(s)…', $total));

        $enqueued = 0;
        $skipped = 0;
        foreach ($customers as $customer) {
            if (!$customer instanceof CustomerInterface) {
                ++$skipped;

                continue;
            }

            if (!$customer instanceof MailchimpAwareInterface) {
                ++$skipped;

                continue;
            }

            try {
                $this->memberEnqueuer->enqueue($customer);
                ++$enqueued;
            } catch (AudienceNotFoundException $e) {
                $this->logger->warning('[Mailchimp] SyncMembers: audience not found for customer #{id}.', [
                    'id' => $customer->getId(),
                    'msg' => $e->getMessage(),
                ]);
                ++$skipped;
            }
        }

        $io->success(sprintf('Enqueued %d, skipped %d out of %d customer(s).', $enqueued, $skipped, $total));

        $this->release();

        return Command::SUCCESS;
    }

    /** @return object[] */
    private function resolveCustomers(InputInterface $input): array
    {
        /** @var string[]|null $customerIds */
        $customerIds = $input->getOption('customer-ids');
        if ($customerIds !== null && $customerIds !== []) {
            $intIds = array_map('intval', $customerIds);

            return $this->customerRepository->findMailchimpByIds($intIds);
        }

        $updatedLastDays = $input->getOption('updated-last-days');
        if ($updatedLastDays !== null) {
            $since = new \DateTimeImmutable(sprintf('-%d days', (int) $updatedLastDays));

            return $this->customerRepository->findMailchimpSyncNeededSince($since);
        }

        return $this->customerRepository->findMailchimpSyncNeeded();
    }
}
