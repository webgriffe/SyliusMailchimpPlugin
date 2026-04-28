<?php

declare(strict_types=1);

namespace Webgriffe\SyliusMailchimpPlugin\Repository;

use Sylius\Component\Core\Repository\CustomerRepositoryInterface;

interface MailchimpCustomerRepositoryInterface extends CustomerRepositoryInterface
{
    /** @return object[] */
    public function findMailchimpSyncNeeded(): array;

    /** @return object[] */
    public function findMailchimpSyncNeededSince(\DateTimeInterface $since): array;

    /**
     * @param int[] $ids
     *
     * @return object[]
     */
    public function findMailchimpByIds(array $ids): array;

    public function countMailchimpSyncedMembers(): int;

    public function countMailchimpMembersWithError(): int;

    public function countMailchimpNeverSyncedMembers(): int;
}
