<?php

declare(strict_types=1);

namespace Webgriffe\SyliusMailchimpPlugin\Enqueuer;

use Psr\Log\LoggerInterface;
use Sylius\Component\Core\Model\CustomerInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Webgriffe\SyliusMailchimpPlugin\Client\MailchimpClientInterface;
use Webgriffe\SyliusMailchimpPlugin\Exception\AudienceNotFoundException;
use Webgriffe\SyliusMailchimpPlugin\Message\Member\MemberCreate;
use Webgriffe\SyliusMailchimpPlugin\Message\Member\MemberRemove;
use Webgriffe\SyliusMailchimpPlugin\Message\Member\MemberUpdate;
use Webgriffe\SyliusMailchimpPlugin\Model\MailchimpAwareInterface;
use Webgriffe\SyliusMailchimpPlugin\Provider\AudienceContextInterface;

final class MemberEnqueuer implements MemberEnqueuerInterface
{
    public function __construct(
        private readonly MessageBusInterface $messageBus,
        private readonly AudienceContextInterface $audienceContext,
        private readonly MailchimpClientInterface $mailchimpClient,
        private readonly LoggerInterface $logger,
    ) {
    }

    #[\Override]
    public function enqueue(CustomerInterface $customer): void
    {
        if (!$customer->isSubscribedToNewsletter()) {
            $this->logger->debug('[Mailchimp] Skipping enqueue for customer #{id}: not subscribed to newsletter.', [
                'id' => $customer->getId(),
            ]);

            return;
        }

        if (!$customer instanceof MailchimpAwareInterface) {
            $this->logger->warning('[Mailchimp] Customer is not MailchimpAwareInterface, skipping enqueue.');

            return;
        }

        $customerId = $customer->getId();
        if (!is_int($customerId)) {
            $this->logger->warning('[Mailchimp] Customer has no integer ID, skipping enqueue.');

            return;
        }

        $email = $customer->getEmail();
        if ($email === null || $email === '') {
            $this->logger->warning('[Mailchimp] Customer #{id} has no email, skipping enqueue.', ['id' => $customerId]);

            return;
        }

        try {
            $listId = $this->audienceContext->getAudienceId();
        } catch (AudienceNotFoundException $e) {
            $this->logger->warning('[Mailchimp] Could not resolve audience for customer #{id}: {msg}', [
                'id' => $customerId,
                'msg' => $e->getMessage(),
            ]);

            return;
        }

        $this->enqueueForList($customer, $customerId, $email, $listId);
    }

    #[\Override]
    public function enqueueForList(CustomerInterface $customer, int $customerId, string $email, string $listId): void
    {
        if (!$customer instanceof MailchimpAwareInterface) {
            return;
        }

        $existingMailchimpId = $customer->getMailchimpId();
        if ($existingMailchimpId !== null && $existingMailchimpId !== '') {
            $this->messageBus->dispatch(new MemberUpdate($customerId, $listId));
            $this->logger->debug('[Mailchimp] Dispatched MemberUpdate for customer #{id}.', ['id' => $customerId]);

            return;
        }

        $subscriberHash = md5(strtolower($email));
        $remoteMember = $this->mailchimpClient->getMember($listId, $subscriberHash);
        if ($remoteMember !== null) {
            $this->messageBus->dispatch(new MemberUpdate($customerId, $listId));
            $this->logger->debug('[Mailchimp] Dispatched MemberUpdate for customer #{id} (existing remote member).', ['id' => $customerId]);
        } else {
            $this->messageBus->dispatch(new MemberCreate($customerId, $listId));
            $this->logger->debug('[Mailchimp] Dispatched MemberCreate for customer #{id}.', ['id' => $customerId]);
        }
    }

    #[\Override]
    public function enqueueRemoval(int $customerId, string $listId, string $email): void
    {
        $subscriberHash = md5(strtolower($email));
        $this->logger->debug('[Mailchimp] Dispatching MemberRemove for customer #{id}.', ['id' => $customerId]);
        $this->messageBus->dispatch(new MemberRemove($customerId, $listId, $subscriberHash));
    }
}
