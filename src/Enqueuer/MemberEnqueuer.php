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
        $context = $this->resolveAudienceContext($customer, 'enqueue');
        if ($context === null) {
            return;
        }

        $email = $customer->getEmail();
        if ($email === null || $email === '') {
            $this->logger->warning('[Mailchimp] Customer #{id} has no email, skipping enqueue.', ['id' => $context['customerId']]);

            return;
        }

        $this->enqueueForList($customer, $context['customerId'], $email, $context['listId']);
    }

    #[\Override]
    public function enqueueForList(CustomerInterface $customer, int $customerId, string $email, string $listId): void
    {
        if (!$customer instanceof MailchimpAwareInterface) {
            return;
        }

        if (!$this->canBeEnqueued($customer)) {
            $this->logger->debug('[Mailchimp] Skipping enqueue for customer #{id}: mailchimp id null or not subscribed to newsletter.', [
                'id' => $customer->getId(),
            ]);

            return;
        }

        $existingMailchimpId = $customer->getMailchimpId();
        if ($existingMailchimpId !== null && $existingMailchimpId !== '') {
            $this->dispatchSafely(new MemberUpdate($customerId, $listId));
            $this->logger->debug('[Mailchimp] Dispatched MemberUpdate for customer #{id}.', ['id' => $customerId]);

            return;
        }

        $subscriberHash = md5(strtolower($email));

        try {
            $remoteMember = $this->mailchimpClient->getMember($listId, $subscriberHash);
        } catch (\Throwable $e) {
            $this->logger->warning('[Mailchimp] Could not check remote member for customer #{id}, assuming new: {msg}', [
                'id' => $customerId,
                'msg' => $e->getMessage(),
            ]);
            $remoteMember = null;
        }

        if ($remoteMember !== null) {
            $this->dispatchSafely(new MemberUpdate($customerId, $listId));
            $this->logger->debug('[Mailchimp] Dispatched MemberUpdate for customer #{id} (existing remote member).', ['id' => $customerId]);
        } else {
            $this->dispatchSafely(new MemberCreate($customerId, $listId));
            $this->logger->debug('[Mailchimp] Dispatched MemberCreate for customer #{id}.', ['id' => $customerId]);
        }
    }

    #[\Override]
    public function enqueueEmailChange(CustomerInterface $customer, string $oldEmail): void
    {
        $context = $this->resolveAudienceContext($customer, 'email change enqueue');
        if ($context === null) {
            return;
        }

        $this->enqueueRemoval($context['customerId'], $context['listId'], $oldEmail);

        if (!$this->canBeEnqueued($customer)) {
            $this->logger->debug('[Mailchimp] Skipping enqueue for customer #{id}: mailchimp id null or not subscribed to newsletter.', [
                'id' => $customer->getId(),
            ]);

            return;
        }

        $newEmail = $customer->getEmail();
        if ($newEmail === null || $newEmail === '') {
            return;
        }

        $this->enqueueForList($customer, $context['customerId'], $newEmail, $context['listId']);
    }

    #[\Override]
    public function enqueueRemoval(int $customerId, string $listId, string $email): void
    {
        $subscriberHash = md5(strtolower($email));
        $this->logger->debug('[Mailchimp] Dispatching MemberRemove for customer #{id}.', ['id' => $customerId]);
        $this->dispatchSafely(new MemberRemove($customerId, $listId, $subscriberHash));
    }

    /**
     * With a synchronous transport, handler exceptions surface here in the middle of a
     * storefront HTTP request: a Mailchimp sync failure must not break the main flow.
     */
    private function dispatchSafely(object $message): void
    {
        try {
            $this->messageBus->dispatch($message);
        } catch (\Throwable $e) {
            $this->logger->error('[Mailchimp] Failed to process {message}: {msg}', [
                'message' => $message::class,
                'msg' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @return array{customerId: int, listId: string}|null
     */
    private function resolveAudienceContext(CustomerInterface $customer, string $operation): ?array
    {
        if (!$customer instanceof MailchimpAwareInterface) {
            $this->logger->warning('[Mailchimp] Customer is not MailchimpAwareInterface, skipping {operation}.', [
                'operation' => $operation,
            ]);

            return null;
        }

        $customerId = $customer->getId();
        if (!is_int($customerId)) {
            $this->logger->warning('[Mailchimp] Customer has no integer ID, skipping {operation}.', [
                'operation' => $operation,
            ]);

            return null;
        }

        try {
            $listId = $this->audienceContext->getAudienceId();
        } catch (AudienceNotFoundException $e) {
            $this->logger->warning('[Mailchimp] Could not resolve audience for customer #{id}: {msg}', [
                'id' => $customerId,
                'msg' => $e->getMessage(),
            ]);

            return null;
        }

        return ['customerId' => $customerId, 'listId' => $listId];
    }

    /**
     * Do not sync customer never synced with Mailchimp or actually subscribed to NL
     */
    private function canBeEnqueued(MailchimpAwareInterface|CustomerInterface $customer): bool
    {
        return $customer->getMailchimpId() !== null || $customer->isSubscribedToNewsletter();
    }
}
