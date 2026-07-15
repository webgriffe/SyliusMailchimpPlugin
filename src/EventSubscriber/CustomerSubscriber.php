<?php

declare(strict_types=1);

namespace Webgriffe\SyliusMailchimpPlugin\EventSubscriber;

use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Sylius\Component\Core\Model\CustomerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\EventDispatcher\GenericEvent;
use Webgriffe\SyliusMailchimpPlugin\Enqueuer\MemberEnqueuerInterface;

final class CustomerSubscriber implements EventSubscriberInterface
{
    /** @var array<int, array{email: string}> */
    private array $emailBeforeUpdate = [];

    public function __construct(
        private readonly MemberEnqueuerInterface $memberEnqueuer,
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

        // This resource event fires before flush, when the UnitOfWork has not yet computed
        // change sets, so compare the current email against the data loaded from the database.
        $originalData = $this->entityManager->getUnitOfWork()->getOriginalEntityData($customer);
        /** @var mixed $originalEmail */
        $originalEmail = $originalData['email'] ?? null;

        if (is_string($originalEmail) && $originalEmail !== '' && $originalEmail !== $customer->getEmail()) {
            $this->emailBeforeUpdate[$customerId] = ['email' => $originalEmail];
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

            $this->memberEnqueuer->enqueueEmailChange($customer, $oldEmail);

            return;
        }

        $this->memberEnqueuer->enqueue($customer);
    }
}
