<?php

declare(strict_types=1);

namespace Webgriffe\SyliusMailchimpPlugin\EventSubscriber;

use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Sylius\Component\Core\Model\CustomerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\EventDispatcher\GenericEvent;
use Symfony\Component\Messenger\MessageBusInterface;
use Webgriffe\SyliusMailchimpPlugin\Enqueuer\MemberEnqueuerInterface;
use Webgriffe\SyliusMailchimpPlugin\Exception\AudienceNotFoundException;
use Webgriffe\SyliusMailchimpPlugin\Message\Member\MemberCreate;
use Webgriffe\SyliusMailchimpPlugin\Message\Member\MemberRemove;
use Webgriffe\SyliusMailchimpPlugin\Model\MailchimpAwareInterface;
use Webgriffe\SyliusMailchimpPlugin\Provider\AudienceContextInterface;

final class CustomerSubscriber implements EventSubscriberInterface
{
    /** @var array<int, array{email: string}> */
    private array $emailBeforeUpdate = [];

    public function __construct(
        private readonly MemberEnqueuerInterface $memberEnqueuer,
        private readonly AudienceContextInterface $audienceContext,
        private readonly MessageBusInterface $messageBus,
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger,
    ) {
    }

    #[\Override]
    public static function getSubscribedEvents(): array
    {
        return [
            'sylius.customer.post_register' => 'onCustomerPostRegister',
            'sylius.customer.pre_update' => 'onCustomerPreUpdate',
            'sylius.customer.post_update' => 'onCustomerPostUpdate',
        ];
    }

    public function onCustomerPostRegister(GenericEvent $event): void
    {
        $customer = $event->getSubject();
        if (!$customer instanceof CustomerInterface) {
            return;
        }

        if (!$customer->isSubscribedToNewsletter()) {
            $this->logger->debug('[Mailchimp] Skipping enqueue for customer on registration: not subscribed to newsletter.');

            return;
        }

        $this->memberEnqueuer->enqueue($customer);
    }

    public function onCustomerPreUpdate(GenericEvent $event): void
    {
        $customer = $event->getSubject();
        if (!$customer instanceof CustomerInterface) {
            return;
        }

        $customerId = $customer->getId();
        if (!is_int($customerId)) {
            return;
        }

        $uow = $this->entityManager->getUnitOfWork();
        $changeSet = $uow->getEntityChangeSet($customer);

        if (isset($changeSet['email']) && is_string($changeSet['email'][0])) {
            $this->emailBeforeUpdate[$customerId] = ['email' => $changeSet['email'][0]];
        }
    }

    public function onCustomerPostUpdate(GenericEvent $event): void
    {
        $customer = $event->getSubject();
        if (!$customer instanceof CustomerInterface) {
            return;
        }

        $customerId = $customer->getId();
        if (!is_int($customerId)) {
            return;
        }

        if (isset($this->emailBeforeUpdate[$customerId])) {
            $oldEmail = $this->emailBeforeUpdate[$customerId]['email'];
            unset($this->emailBeforeUpdate[$customerId]);

            if (!$customer instanceof MailchimpAwareInterface) {
                $this->memberEnqueuer->enqueue($customer);

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

            $subscriberHash = md5(strtolower($oldEmail));
            $this->messageBus->dispatch(new MemberRemove($customerId, $listId, $subscriberHash));

            $newEmail = $customer->getEmail();
            if ($newEmail !== null && $newEmail !== '' && $customer->isSubscribedToNewsletter()) {
                $this->messageBus->dispatch(new MemberCreate($customerId, $listId));
            }

            return;
        }

        if (!$customer->isSubscribedToNewsletter()) {
            $this->logger->debug('[Mailchimp] Skipping enqueue for customer #{id} on update: not subscribed to newsletter.', [
                'id' => $customerId,
            ]);

            return;
        }

        $this->memberEnqueuer->enqueue($customer);
    }
}
