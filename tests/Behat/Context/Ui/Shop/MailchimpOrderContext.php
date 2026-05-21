<?php

declare(strict_types=1);

namespace Tests\Webgriffe\SyliusMailchimpPlugin\Behat\Context\Ui\Shop;

use Behat\Behat\Context\Context;
use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use Doctrine\ORM\EntityManagerInterface;
use Sylius\Behat\Service\SharedStorageInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\GenericEvent;
use Tests\Webgriffe\SyliusMailchimpPlugin\Behat\Client\StubMailchimpClient;
use Webgriffe\SyliusMailchimpPlugin\Model\MailchimpOrderAwareInterface;
use Webmozart\Assert\Assert;

final class MailchimpOrderContext implements Context
{
    private ?string $syncedCartId = null;

    public function __construct(
        private readonly StubMailchimpClient $stubMailchimpClient,
        private readonly SharedStorageInterface $sharedStorage,
        private readonly EntityManagerInterface $entityManager,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
    }

    /** @BeforeScenario */
    public function resetStub(BeforeScenarioScope $scope): void
    {
        $this->stubMailchimpClient->reset();
        $this->syncedCartId = null;
    }

    /**
     * @Given the cart has been synced to Mailchimp
     */
    public function theCartHasBeenSyncedToMailchimp(): void
    {
        $order = $this->getCurrentCart();
        $cartId = (string) $order->getId();
        Assert::isInstanceOf($order, MailchimpOrderAwareInterface::class);
        $order->setMailchimpCartId($cartId);
        $this->entityManager->flush();
        $this->syncedCartId = $cartId;
    }

    /**
     * @Given the order has already been synced to Mailchimp
     */
    public function theOrderHasAlreadyBeenSyncedToMailchimp(): void
    {
        $order = $this->getCurrentCart();
        $orderId = (string) $order->getId();
        Assert::isInstanceOf($order, MailchimpOrderAwareInterface::class);
        $order->setMailchimpCartId($orderId);
        $order->setMailchimpOrderId($orderId);
        $this->entityManager->flush();
        $this->syncedCartId = $orderId;
    }

    /**
     * @When the checkout is completed
     */
    public function theCheckoutIsCompleted(): void
    {
        $order = $this->getCurrentCart();
        $this->eventDispatcher->dispatch(new GenericEvent($order), 'sylius.order.post_complete');
    }

    /**
     * @Then the order should be synced to Mailchimp
     */
    public function theOrderShouldBeSyncedToMailchimp(): void
    {
        Assert::notEmpty(
            $this->stubMailchimpClient->getUpsertOrderCalls(),
            'Expected upsertOrder to be called at least once, but it was not called.',
        );
    }

    /**
     * @Then the order should not be synced to Mailchimp
     */
    public function theOrderShouldNotBeSyncedToMailchimp(): void
    {
        Assert::isEmpty(
            $this->stubMailchimpClient->getUpsertOrderCalls(),
            'Expected upsertOrder not to be called, but it was called.',
        );
    }

    /**
     * @Then the last order sync should have :count line(s)
     */
    public function theLastOrderSyncShouldHaveLines(int $count): void
    {
        $order = $this->stubMailchimpClient->getLastUpsertOrderCall();
        Assert::notNull($order, 'Expected upsertOrder to be called at least once, but it was not called.');
        Assert::count(
            $order->lines,
            $count,
            sprintf('Expected last order sync to have %d line(s), got %d.', $count, count($order->lines)),
        );
    }

    /**
     * @Then the last order sync should reference the synced cart
     */
    public function theLastOrderSyncShouldReferenceTheSyncedCart(): void
    {
        $order = $this->stubMailchimpClient->getLastUpsertOrderCall();
        Assert::notNull($order, 'Expected upsertOrder to be called at least once, but it was not called.');
        Assert::same(
            $order->cartId,
            $this->syncedCartId,
            sprintf('Expected order cart_id to be "%s", got "%s".', $this->syncedCartId, $order->cartId),
        );
    }

    private function getCurrentCart(): OrderInterface
    {
        /** @var OrderInterface $order */
        $order = $this->sharedStorage->get('order');

        return $order;
    }
}
