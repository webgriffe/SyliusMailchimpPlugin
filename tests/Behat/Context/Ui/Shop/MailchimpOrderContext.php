<?php

declare(strict_types=1);

namespace Tests\Webgriffe\SyliusMailchimpPlugin\Behat\Context\Ui\Shop;

use Behat\Behat\Context\Context;
use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use Behat\MinkExtension\Context\RawMinkContext;
use Doctrine\ORM\EntityManagerInterface;
use Sylius\Behat\Service\SharedStorageInterface;
use Sylius\Component\Core\Model\CustomerInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Repository\CustomerRepositoryInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\GenericEvent;
use Tests\Webgriffe\SyliusMailchimpPlugin\Stub\Mailchimp\StubMailchimpClient;
use Webgriffe\SyliusMailchimpPlugin\Model\MailchimpOrderAwareInterface;
use Webmozart\Assert\Assert;

final class MailchimpOrderContext extends RawMinkContext implements Context
{
    private ?string $syncedCartId = null;

    public function __construct(
        private readonly StubMailchimpClient $stubMailchimpClient,
        private readonly SharedStorageInterface $sharedStorage,
        private readonly EntityManagerInterface $entityManager,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly CustomerRepositoryInterface $customerRepository,
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
     * @When I check the newsletter subscription checkbox
     */
    public function iCheckTheNewsletterSubscriptionCheckbox(): void
    {
        $checkbox = $this->getSession()->getPage()->find('css', '.js-mailchimp-newsletter-checkbox');
        Assert::notNull($checkbox, 'Could not find the newsletter subscription checkbox on the page.');
        $checkbox->check();
    }

    /**
     * @Then the customer should be subscribed to the newsletter
     */
    public function theCustomerShouldBeSubscribedToTheNewsletter(): void
    {
        $order = $this->getCurrentCart();
        $email = $order->getCustomer()?->getEmail();
        Assert::notNull($email, 'Expected order to have a customer email.');

        $customer = $this->waitForSubscribedCustomer($email);

        Assert::notNull($customer, sprintf('Customer with email "%s" was never marked as subscribed to the newsletter.', $email));
        Assert::notEmpty(
            $this->stubMailchimpClient->getUpsertMemberCalls(),
            'Expected upsertMember to be called at least once, but it was not called.',
        );
    }

    private function waitForSubscribedCustomer(string $email): ?CustomerInterface
    {
        $deadline = microtime(true) + 5;
        do {
            $this->entityManager->clear();
            $customer = $this->customerRepository->findOneBy(['email' => $email]);
            if ($customer instanceof CustomerInterface && $customer->isSubscribedToNewsletter()) {
                return $customer;
            }
            usleep(100000);
        } while (microtime(true) < $deadline);

        return null;
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
            $order->getItems()->toArray(),
            $count,
            sprintf('Expected last order sync to have %d line(s), got %d.', $count, $order->getItems()->count()),
        );
    }

    /**
     * @Then the last order sync should reference the synced cart
     */
    public function theLastOrderSyncShouldReferenceTheSyncedCart(): void
    {
        $order = $this->stubMailchimpClient->getLastUpsertOrderCall();
        Assert::notNull($order, 'Expected upsertOrder to be called at least once, but it was not called.');
        Assert::isInstanceOf($order, MailchimpOrderAwareInterface::class);
        $cartId = $order->getMailchimpCartId();
        Assert::same(
            $cartId,
            $this->syncedCartId,
            sprintf('Expected order cart_id to be "%s", got "%s".', $this->syncedCartId, $cartId),
        );
    }

    private function getCurrentCart(): OrderInterface
    {
        /** @var OrderInterface $order */
        $order = $this->sharedStorage->get('order');

        return $order;
    }
}
