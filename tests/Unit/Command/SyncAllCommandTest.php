<?php

declare(strict_types=1);

namespace Tests\Webgriffe\SyliusMailchimpPlugin\Unit\Command;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\CommandTester;
use Webgriffe\SyliusMailchimpPlugin\Command\SyncAllCommand;

final class SyncAllCommandTest extends TestCase
{
    private const SUB_COMMANDS = [
        'webgriffe:sylius-mailchimp:sync-members',
        'webgriffe:sylius-mailchimp:sync-stores',
        'webgriffe:sylius-mailchimp:sync-products',
        'webgriffe:sylius-mailchimp:sync-carts',
        'webgriffe:sylius-mailchimp:sync-orders',
    ];

    public function test_runs_all_sync_commands_in_order(): void
    {
        $executed = [];
        $application = $this->createApplicationWithSubCommands($executed);

        $tester = new CommandTester($application->find('webgriffe:sylius-mailchimp:sync-all'));
        $tester->execute([]);

        self::assertSame(0, $tester->getStatusCode());
        self::assertSame(self::SUB_COMMANDS, $executed);
    }

    public function test_stops_and_fails_when_a_sync_command_fails(): void
    {
        $executed = [];
        $application = $this->createApplicationWithSubCommands(
            $executed,
            ['webgriffe:sylius-mailchimp:sync-products' => Command::FAILURE],
        );

        $tester = new CommandTester($application->find('webgriffe:sylius-mailchimp:sync-all'));
        $tester->execute([]);

        self::assertSame(1, $tester->getStatusCode());
        self::assertSame([
            'webgriffe:sylius-mailchimp:sync-members',
            'webgriffe:sylius-mailchimp:sync-stores',
            'webgriffe:sylius-mailchimp:sync-products',
        ], $executed);
    }

    /**
     * @param list<string> $executed
     * @param array<string, int> $exitCodes
     */
    private function createApplicationWithSubCommands(array &$executed, array $exitCodes = []): Application
    {
        $application = new Application();
        $application->setAutoExit(false);

        foreach (self::SUB_COMMANDS as $name) {
            $application->add($this->createRecordingCommand($name, $executed, $exitCodes[$name] ?? Command::SUCCESS));
        }
        $application->add(new SyncAllCommand());

        return $application;
    }

    /** @param list<string> $executed */
    private function createRecordingCommand(string $name, array &$executed, int $exitCode): Command
    {
        return new class($name, $executed, $exitCode) extends Command {
            /** @var list<string> */
            private array $executed;

            /** @param list<string> $executed */
            public function __construct(
                string $name,
                array &$executed,
                private readonly int $exitCode,
            ) {
                parent::__construct($name);
                $this->executed = &$executed;
            }

            #[\Override]
            protected function execute(InputInterface $input, OutputInterface $output): int
            {
                $this->executed[] = (string) $this->getName();

                return $this->exitCode;
            }
        };
    }
}
