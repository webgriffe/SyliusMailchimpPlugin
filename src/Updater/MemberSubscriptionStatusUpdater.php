<?php

declare(strict_types=1);

namespace Webgriffe\SyliusMailchimpPlugin\Updater;

use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Sylius\Component\Core\Model\CustomerInterface;
use Sylius\Component\Core\Repository\CustomerRepositoryInterface;
use Webgriffe\SyliusMailchimpPlugin\Model\MailchimpAwareInterface;

final class MemberSubscriptionStatusUpdater
{
    public function __construct(
        private readonly CustomerRepositoryInterface $customerRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Updates a customer's Mailchimp subscription status based on the webhook event type.
     *
     * Supported types: subscribe, unsubscribe, profile, cleaned, upemail, campaign.
     */
    public function update(string $type, string $email, string $listId): void
    {
        $customer = $this->findByEmail($email);
        if ($customer === null) {
            $this->logger->info('[Mailchimp] Webhook type={type}: no local customer found for email {email}.', [
                'type' => $type,
                'email' => $email,
            ]);

            return;
        }

        if (!$customer instanceof MailchimpAwareInterface) {
            return;
        }

        match ($type) {
            'subscribe' => $this->handleSubscribe($customer),
            'unsubscribe', 'cleaned' => $this->handleUnsubscribe($customer),
            default => $this->handleProfileUpdate($customer),
        };

        $this->entityManager->flush();
    }

    private function findByEmail(string $email): ?CustomerInterface
    {
        /** @var CustomerInterface|null $customer */
        $customer = $this->customerRepository->findOneBy(['email' => $email]);

        return $customer;
    }

    private function handleSubscribe(CustomerInterface&MailchimpAwareInterface $customer): void
    {
        $subscriberHash = md5(strtolower((string) $customer->getEmail()));
        $customer->setMailchimpId($subscriberHash);
        $customer->setMailchimpSyncedAt(new \DateTimeImmutable());
        $customer->setMailchimpError(null);

        $this->logger->info('[Mailchimp] Webhook subscribe: updated mailchimpId for customer #{id}.', [
            'id' => $customer->getId(),
        ]);
    }

    private function handleUnsubscribe(CustomerInterface&MailchimpAwareInterface $customer): void
    {
        $customer->setMailchimpId(null);
        $customer->setMailchimpSyncedAt(new \DateTimeImmutable());
        $customer->setMailchimpError(null);

        $this->logger->info('[Mailchimp] Webhook unsubscribe/cleaned: cleared mailchimpId for customer #{id}.', [
            'id' => $customer->getId(),
        ]);
    }

    private function handleProfileUpdate(CustomerInterface&MailchimpAwareInterface $customer): void
    {
        $customer->setMailchimpSyncedAt(new \DateTimeImmutable());
        $customer->setMailchimpError(null);

        $this->logger->info('[Mailchimp] Webhook profile/campaign/upemail: updated syncedAt for customer #{id}.', [
            'id' => $customer->getId(),
        ]);
    }
}
