<?php

declare(strict_types=1);

namespace Webgriffe\SyliusMailchimpPlugin\Repository;

use Sylius\Component\Core\Repository\OrderRepositoryInterface;

interface MailchimpOrderRepositoryInterface extends OrderRepositoryInterface
{
    /** @return object[] */
    public function findMailchimpCartSyncNeeded(): array;

    /** @return object[] */
    public function findMailchimpCartSyncNeededUpdatedSince(\DateTimeInterface $since): array;

    /** @return object[] */
    public function findMailchimpOrderSyncNeeded(): array;

    /** @return object[] */
    public function findMailchimpOrderSyncNeededUpdatedSince(\DateTimeInterface $since): array;

    public function countMailchimpPendingCarts(): int;

    public function countMailchimpPendingOrders(): int;
}
