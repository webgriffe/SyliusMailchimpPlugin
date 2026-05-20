<?php

declare(strict_types=1);

namespace Tests\Webgriffe\SyliusMailchimpPlugin\Behat\Context\Ui\Shop;

use Behat\Behat\Context\Context;
use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use Tests\Webgriffe\SyliusMailchimpPlugin\Behat\Client\StubMailchimpClient;
use Webmozart\Assert\Assert;

final class MailchimpCartContext implements Context
{
    public function __construct(
        private readonly StubMailchimpClient $stubMailchimpClient,
    ) {
    }

    /** @BeforeScenario */
    public function resetStub(BeforeScenarioScope $scope): void
    {
        $this->stubMailchimpClient->reset();
    }

    /**
     * @Then the cart should be synced to Mailchimp
     */
    public function theCartShouldBeSyncedToMailchimp(): void
    {
        Assert::notEmpty(
            $this->stubMailchimpClient->getUpsertCartCalls(),
            'Expected upsertCart to be called at least once, but it was not called.',
        );
    }

    /**
     * @Then the cart should not be synced to Mailchimp
     */
    public function theCartShouldNotBeSyncedToMailchimp(): void
    {
        Assert::isEmpty(
            $this->stubMailchimpClient->getUpsertCartCalls(),
            'Expected upsertCart not to be called, but it was called.',
        );
    }
}
