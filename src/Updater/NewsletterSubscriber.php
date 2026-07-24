<?php

declare(strict_types=1);

namespace Webgriffe\SyliusMailchimpPlugin\Updater;

use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Sylius\Component\Core\Model\CustomerInterface;
use Sylius\Component\Core\Repository\CustomerRepositoryInterface;
use Webgriffe\SyliusMailchimpPlugin\Client\Exception\ComplianceStateException;
use Webgriffe\SyliusMailchimpPlugin\Client\MailchimpClientInterface;
use Webgriffe\SyliusMailchimpPlugin\Model\MailchimpAwareInterface;
use Webgriffe\SyliusMailchimpPlugin\ValueObject\Member;
use Webgriffe\SyliusMailchimpPlugin\ValueObject\MergeFields;

/**
 * Subscribes an email to a Mailchimp audience synchronously, in-process (no Messenger),
 * so the caller can report the real outcome to the end user. See docs/adr/0002.
 */
final class NewsletterSubscriber implements NewsletterSubscriberInterface
{
    public function __construct(
        private readonly MailchimpClientInterface $mailchimpClient,
        private readonly string $memberDefaultStatus,
        private readonly CustomerRepositoryInterface $customerRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function subscribe(string $email, string $listId): void
    {
        $member = new Member(
            emailAddress: $email,
            status: $this->memberDefaultStatus,
            mergeFields: new MergeFields('', ''),
            tags: [],
            interests: [],
            language: '',
            ipSignup: '',
        );

        $customer = $this->customerRepository->findOneBy(['email' => $email]);

        try {
            $mailchimpId = $this->mailchimpClient->upsertMember($listId, $member);
            $this->logger->info('[Mailchimp] Newsletter subscribed: {email} to list {list}.', [
                'email' => $email,
                'list' => $listId,
            ]);

            if ($customer instanceof CustomerInterface && $customer instanceof MailchimpAwareInterface) {
                $customer->setMailchimpId($mailchimpId);
                $customer->setMailchimpSyncedAt(new \DateTimeImmutable());
                $customer->setMailchimpError(null);
                $customer->setSubscribedToNewsletter(true);
                $this->entityManager->flush();
            }
        } catch (ComplianceStateException $e) {
            $this->logger->warning('[Mailchimp] Newsletter compliance state for {email}: {msg}', [
                'email' => $email,
                'msg' => $e->getMessage(),
            ]);

            if ($customer instanceof MailchimpAwareInterface) {
                $customer->setMailchimpError($e->getMessage());
                $this->entityManager->flush();
            }

            throw $e;
        } catch (\Throwable $e) {
            $this->logger->error('[Mailchimp] Newsletter subscribe failed for {email}: {msg}', [
                'email' => $email,
                'msg' => $e->getMessage(),
            ]);

            if ($customer instanceof MailchimpAwareInterface) {
                $customer->setMailchimpError($e->getMessage());
                $this->entityManager->flush();
            }

            throw $e;
        }
    }
}
