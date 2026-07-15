<?php

declare(strict_types=1);

namespace Tests\Webgriffe\SyliusMailchimpPlugin\Behat\Context\Setup;

use Behat\Behat\Context\Context;
use Doctrine\ORM\EntityManagerInterface;
use Sylius\Component\Core\Model\CustomerInterface;
use Sylius\Component\Core\Repository\CustomerRepositoryInterface;
use Webgriffe\SyliusMailchimpPlugin\Model\MailchimpAwareInterface;
use Webmozart\Assert\Assert;

final class MailchimpCustomerContext implements Context
{
    public function __construct(
        private readonly CustomerRepositoryInterface $customerRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @Given the customer :email is subscribed to the newsletter
     */
    public function theCustomerIsSubscribedToTheNewsletter(string $email): void
    {
        $customer = $this->getCustomerByEmail($email);
        $customer->setSubscribedToNewsletter(true);

        $this->entityManager->flush();
    }

    /**
     * @Given the customer :email is already synced to Mailchimp
     */
    public function theCustomerIsAlreadySyncedToMailchimp(string $email): void
    {
        $customer = $this->getCustomerByEmail($email);
        Assert::isInstanceOf($customer, MailchimpAwareInterface::class);
        $customer->setMailchimpId(md5(strtolower($email)));
        $customer->setMailchimpSyncedAt(new \DateTimeImmutable());

        $this->entityManager->flush();
    }

    private function getCustomerByEmail(string $email): CustomerInterface
    {
        $customer = $this->customerRepository->findOneBy(['email' => $email]);
        Assert::isInstanceOf($customer, CustomerInterface::class, sprintf('Customer with email "%s" not found.', $email));

        return $customer;
    }
}
