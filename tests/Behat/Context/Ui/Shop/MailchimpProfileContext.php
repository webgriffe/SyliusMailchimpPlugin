<?php

declare(strict_types=1);

namespace Tests\Webgriffe\SyliusMailchimpPlugin\Behat\Context\Ui\Shop;

use Behat\Behat\Context\Context;
use Tests\Webgriffe\SyliusMailchimpPlugin\Stub\Mailchimp\StubMailchimpClient;
use Webmozart\Assert\Assert;

final class MailchimpProfileContext implements Context
{
    public function __construct(
        private readonly StubMailchimpClient $stubMailchimpClient,
    ) {
    }

    /**
     * @Then the Mailchimp member with email :email should have been removed
     */
    public function theMailchimpMemberWithEmailShouldHaveBeenRemoved(string $email): void
    {
        $expectedSubscriberHash = md5(strtolower($email));
        foreach ($this->stubMailchimpClient->getRemoveMemberCalls() as $call) {
            if ($call['subscriberHash'] === $expectedSubscriberHash) {
                return;
            }
        }

        throw new \RuntimeException(sprintf('Expected removeMember to be called for email "%s", but it was not.', $email));
    }

    /**
     * @Then no Mailchimp member should have been removed
     */
    public function noMailchimpMemberShouldHaveBeenRemoved(): void
    {
        Assert::isEmpty(
            $this->stubMailchimpClient->getRemoveMemberCalls(),
            'Expected removeMember not to be called, but it was called.',
        );
    }

    /**
     * @Then the customer with email :email should be synced to Mailchimp
     */
    public function theCustomerWithEmailShouldBeSyncedToMailchimp(string $email): void
    {
        foreach ($this->stubMailchimpClient->getUpsertMemberCalls() as $call) {
            if ($call['member']->emailAddress === $email) {
                return;
            }
        }

        throw new \RuntimeException(sprintf('Expected upsertMember to be called for email "%s", but it was not.', $email));
    }
}
