<?php

declare(strict_types=1);

namespace Webgriffe\SyliusMailchimpPlugin\Repository;

use Sylius\Component\Core\Repository\OrderRepositoryInterface;

interface MailchimpOrderRepositoryInterface extends OrderRepositoryInterface
{
    /** @return object[] */
    public function findMailchimpCartSyncNeeded(bool $includeFailed = false): array;

    /** @return object[] */
    public function findMailchimpCartSyncNeededUpdatedSince(\DateTimeInterface $since, bool $includeFailed = false): array;

    /** @return object[] */
    public function findMailchimpOrderSyncNeeded(?\DateTimeInterface $updatedSince = null, bool $includeFailed = false): array;

    /** @return object[] */
    public function findMailchimpOrderSyncable(bool $includeFailed = false): array;

    /** @return object[] */
    public function findMailchimpOrderSyncNeededUpdatedSince(\DateTimeInterface $since, bool $includeFailed = false): array;

    public function countMailchimpPendingCarts(): int;

    public function countMailchimpPendingOrders(): int;

    /** @return object[] */
    public function findAbandonedCartsByCustomer(\Sylius\Component\Core\Model\CustomerInterface $customer): array;
}
