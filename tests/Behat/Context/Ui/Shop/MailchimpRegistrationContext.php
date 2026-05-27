<?php

declare(strict_types=1);

namespace Tests\Webgriffe\SyliusMailchimpPlugin\Behat\Context\Ui\Shop;

use Behat\Behat\Context\Context;
use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use Tests\Webgriffe\SyliusMailchimpPlugin\Stub\Mailchimp\StubMailchimpClient;
use Webmozart\Assert\Assert;

final class MailchimpRegistrationContext implements Context
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
     * @Then the customer should be synced to Mailchimp
     */
    public function theCustomerShouldBeSyncedToMailchimp(): void
    {
        Assert::notEmpty(
            $this->stubMailchimpClient->getUpsertMemberCalls(),
            'Expected upsertMember to be called at least once, but it was not called.',
        );
    }

    /**
     * @Then the customer should not be synced to Mailchimp
     */
    public function theCustomerShouldNotBeSyncedToMailchimp(): void
    {
        Assert::isEmpty(
            $this->stubMailchimpClient->getUpsertMemberCalls(),
            'Expected upsertMember not to be called, but it was called.',
        );
    }
}
