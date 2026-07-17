<?php

declare(strict_types=1);

namespace Webgriffe\SyliusMailchimpPlugin\MessageHandler\Member;

use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Sylius\Component\Core\Model\CustomerInterface;
use Sylius\Component\Core\Repository\CustomerRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Webgriffe\SyliusMailchimpPlugin\Client\Exception\ComplianceStateException;
use Webgriffe\SyliusMailchimpPlugin\Client\MailchimpClientInterface;
use Webgriffe\SyliusMailchimpPlugin\Exception\MissingCustomerEmailException;
use Webgriffe\SyliusMailchimpPlugin\Mapper\MemberMapperInterface;
use Webgriffe\SyliusMailchimpPlugin\Message\Member\MemberUpdate;
use Webgriffe\SyliusMailchimpPlugin\Model\MailchimpAwareInterface;
use Webgriffe\SyliusMailchimpPlugin\Util\MailchimpErrorClassifier;

#[AsMessageHandler]
final class MemberUpdateHandler
{
    public function __construct(
        private readonly CustomerRepositoryInterface $customerRepository,
        private readonly MemberMapperInterface $memberMapper,
        private readonly MailchimpClientInterface $mailchimpClient,
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(MemberUpdate $message): void
    {
        $customer = $this->customerRepository->find($message->customerId);
        if ($customer === null) {
            $this->logger->warning('[Mailchimp] Customer #{id} not found, skipping MemberUpdate.', ['id' => $message->customerId]);

            return;
        }

        if (!$customer instanceof MailchimpAwareInterface) {
            $this->logger->warning('[Mailchimp] Customer #{id} does not implement MailchimpAwareInterface.', ['id' => $message->customerId]);

            return;
        }

        if (!$customer instanceof CustomerInterface) {
            $this->logger->warning('[Mailchimp] Customer #{id} is not a CustomerInterface, skipping.', ['id' => $message->customerId]);

            return;
        }

        if (!$customer->isSubscribedToNewsletter()) {
            $this->logger->debug('[Mailchimp] Customer #{id} is not subscribed to newsletter, skipping MemberUpdate.', ['id' => $message->customerId]);

            return;
        }

        try {
            $member = $this->memberMapper->map($customer, $message->listId);
            $mailchimpId = $this->mailchimpClient->upsertMember($message->listId, $member);
            $customer->setMailchimpId($mailchimpId);
            $customer->setMailchimpSyncedAt(new \DateTimeImmutable());
            $customer->setMailchimpError(null);
            $this->entityManager->flush();

            $this->logger->info('[Mailchimp] Member updated for customer #{id}.', ['id' => $message->customerId]);
        } catch (ComplianceStateException $e) {
            $customer->setMailchimpError($e->getMessage());
            $this->entityManager->flush();
            $this->logger->warning('[Mailchimp] Compliance state for customer #{id}: {msg}', ['id' => $message->customerId, 'msg' => $e->getMessage()]);
        } catch (MissingCustomerEmailException $e) {
            $this->logger->warning('[Mailchimp] Missing email for customer #{id}: {msg}', ['id' => $message->customerId, 'msg' => $e->getMessage()]);
        } catch (\Throwable $e) {
            $this->logger->error('[Mailchimp] Failed to sync member for customer #{id}: {msg}', ['id' => $message->customerId, 'msg' => $e->getMessage()]);
            if (!MailchimpErrorClassifier::isPermanent($e)) {
                throw $e;
            }

            try {
                $customer->setMailchimpError($e->getMessage());
                $this->entityManager->flush();
            } catch (\Throwable $flushError) {
                $this->logger->error('[Mailchimp] Could not persist member sync error: {msg}', ['msg' => $flushError->getMessage()]);
            }
        }
    }
}
