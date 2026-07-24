<?php

declare(strict_types=1);

namespace Webgriffe\SyliusMailchimpPlugin\MessageHandler\Newsletter;

use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Sylius\Component\Core\Model\CustomerInterface;
use Sylius\Component\Core\Repository\CustomerRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Webgriffe\SyliusMailchimpPlugin\Client\Exception\ComplianceStateException;
use Webgriffe\SyliusMailchimpPlugin\Client\MailchimpClientInterface;
use Webgriffe\SyliusMailchimpPlugin\Message\Newsletter\NewsletterSubscribe;
use Webgriffe\SyliusMailchimpPlugin\Model\MailchimpAwareInterface;
use Webgriffe\SyliusMailchimpPlugin\Util\MailchimpErrorClassifier;
use Webgriffe\SyliusMailchimpPlugin\ValueObject\Member;
use Webgriffe\SyliusMailchimpPlugin\ValueObject\MergeFields;

#[AsMessageHandler]
final class NewsletterSubscribeHandler
{
    public function __construct(
        private readonly MailchimpClientInterface $mailchimpClient,
        private readonly string $memberDefaultStatus,
        private readonly CustomerRepositoryInterface $customerRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(NewsletterSubscribe $message): void
    {
        $member = new Member(
            emailAddress: $message->email,
            status: $this->memberDefaultStatus,
            mergeFields: new MergeFields('', ''),
            tags: [],
            interests: [],
            language: '',
            ipSignup: '',
        );

        $customer = $this->customerRepository->findOneBy(['email' => $message->email]);

        try {
            $mailchimpId = $this->mailchimpClient->upsertMember($message->listId, $member);
            $this->logger->info('[Mailchimp] Newsletter subscribed: {email} to list {list}.', [
                'email' => $message->email,
                'list' => $message->listId,
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
                'email' => $message->email,
                'msg' => $e->getMessage(),
            ]);

            if ($customer instanceof MailchimpAwareInterface) {
                $customer->setMailchimpError($e->getMessage());
                $this->entityManager->flush();
            }

            throw $e;
        } catch (\Throwable $e) {
            $this->logger->error('[Mailchimp] Newsletter subscribe failed for {email}: {msg}', [
                'email' => $message->email,
                'msg' => $e->getMessage(),
            ]);

            if (!MailchimpErrorClassifier::isPermanent($e)) {
                throw $e;
            }

            if ($customer instanceof MailchimpAwareInterface) {
                $customer->setMailchimpError($e->getMessage());
                $this->entityManager->flush();
            }
        }
    }
}
