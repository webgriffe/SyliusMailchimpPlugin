<?php

declare(strict_types=1);

namespace Webgriffe\SyliusMailchimpPlugin\Enqueuer;

use Sylius\Component\Core\Model\CustomerInterface;

interface MemberEnqueuerInterface
{
    public function enqueue(CustomerInterface $customer): void;

    public function enqueueForList(CustomerInterface $customer, int $customerId, string $email, string $listId): void;

    public function enqueueRemoval(int $customerId, string $listId, string $email): void;

    /**
     * Handles a customer email change: removes the old Mailchimp entry and, if the customer
     * is subscribed to the newsletter, enqueues creation of the new entry.
     */
    public function enqueueEmailChange(CustomerInterface $customer, string $oldEmail): void;
}
