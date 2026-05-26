<?php

declare(strict_types=1);

namespace Tests\Webgriffe\SyliusMailchimpPlugin\Behat\Context\Console;

use Behat\Behat\Context\Context;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\ApplicationTester;
use Symfony\Component\HttpKernel\KernelInterface;
use Webgriffe\SyliusMailchimpPlugin\Command\SyncCartsCommand;

final readonly class MailchimpConsoleCartContext implements Context
{
    public function __construct(private KernelInterface $kernel, private SyncCartsCommand $command)
    {
    }

    /**
     * @When I synchronize carts with Mailchimp
     */
    public function iSynchronizeCartsWithMailchimp(): void
    {
        $application = new Application($this->kernel);
        $application->setAutoExit(false);
        $application->add($this->command);
        $applicationTester = new ApplicationTester($application);
        $applicationTester->run(['command' => $this->command::getDefaultName(), '--updated-last-days' => 1]);
    }
}
