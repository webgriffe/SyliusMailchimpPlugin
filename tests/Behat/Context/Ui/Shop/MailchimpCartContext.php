<?php

declare(strict_types=1);

namespace Tests\Webgriffe\SyliusMailchimpPlugin\Behat\Context\Ui\Shop;

use Behat\Behat\Context\Context;
use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use Tests\Webgriffe\SyliusMailchimpPlugin\Behat\Client\StubMailchimpClient;
use Webgriffe\SyliusMailchimpPlugin\ValueObject\Cart;
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

    /**
     * @Then the last cart sync should have :count line(s)
     */
    public function theLastCartSyncShouldHaveLines(int $count): void
    {
        $cart = $this->getLastUpsertedCart();
        Assert::count(
            $cart->lines,
            $count,
            sprintf('Expected last cart sync to have %d line(s), got %d.', $count, count($cart->lines)),
        );
    }

    /**
     * @Then the last cart sync line should have quantity :quantity
     */
    public function theLastCartSyncLineShouldHaveQuantity(int $quantity): void
    {
        $cart = $this->getLastUpsertedCart();
        Assert::notEmpty($cart->lines, 'Expected at least one line in the last cart sync, but there are none.');

        $line = $cart->lines[0];
        Assert::same(
            $line->quantity,
            $quantity,
            sprintf('Expected cart line quantity to be %d, got %d.', $quantity, $line->quantity),
        );
    }

    /**
     * @Then the cart should be removed from Mailchimp
     */
    public function theCartShouldBeRemovedFromMailchimp(): void
    {
        Assert::notEmpty(
            $this->stubMailchimpClient->getRemoveCartCalls(),
            'Expected removeCart to be called at least once, but it was not called.',
        );
    }

    /**
     * @Then the cart should not be removed from Mailchimp
     */
    public function theCartShouldNotBeRemovedFromMailchimp(): void
    {
        Assert::isEmpty(
            $this->stubMailchimpClient->getRemoveCartCalls(),
            'Expected removeCart not to be called, but it was called.',
        );
    }

    private function getLastUpsertedCart(): Cart
    {
        $calls = $this->stubMailchimpClient->getUpsertCartCalls();
        Assert::notEmpty($calls, 'Expected upsertCart to be called at least once, but it was not called.');

        return $calls[array_key_last($calls)]['cart'];
    }
}
