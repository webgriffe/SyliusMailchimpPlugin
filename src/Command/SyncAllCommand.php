<?php

declare(strict_types=1);

namespace Webgriffe\SyliusMailchimpPlugin\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'webgriffe:sylius-mailchimp:sync-all',
    description: 'Synchronizes all Mailchimp data: members, stores, products, carts, and orders.',
)]
final class SyncAllCommand extends Command
{
    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $application = $this->getApplication();
        if ($application === null) {
            $io->error('Application not found.');

            return Command::FAILURE;
        }

        $commands = [
            'webgriffe:sylius-mailchimp:sync-members',
            'webgriffe:sylius-mailchimp:sync-stores',
            'webgriffe:sylius-mailchimp:sync-products',
            'webgriffe:sylius-mailchimp:sync-carts',
            'webgriffe:sylius-mailchimp:sync-orders',
        ];

        foreach ($commands as $commandName) {
            $io->section(sprintf('Running: %s', $commandName));
            $exitCode = $application->find($commandName)->run(new ArrayInput([]), $output);
            if ($exitCode !== Command::SUCCESS) {
                $io->error(sprintf('Command %s failed with exit code %d.', $commandName, $exitCode));

                return Command::FAILURE;
            }
        }

        $io->success('All Mailchimp sync commands completed successfully.');

        return Command::SUCCESS;
    }
}
