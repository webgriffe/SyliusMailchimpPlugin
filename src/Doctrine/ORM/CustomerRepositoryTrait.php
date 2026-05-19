<?php

declare(strict_types=1);

namespace Webgriffe\SyliusMailchimpPlugin\Doctrine\ORM;

use Doctrine\ORM\QueryBuilder;

trait CustomerRepositoryTrait
{
    public function createMailchimpRelevantQueryBuilder(string $alias): QueryBuilder
    {
        return $this->createQueryBuilder($alias)
            ->leftJoin(sprintf('%s.user', $alias), 'user')
            ->andWhere(sprintf(
                '%s.subscribedToNewsletter = :subscribed OR %s.mailchimpId IS NOT NULL',
                $alias,
                $alias,
            ))
            ->setParameter('subscribed', true)
        ;
    }

    public function createMailchimpSyncNeededQueryBuilder(string $alias): QueryBuilder
    {
        return $this->createQueryBuilder($alias)
            ->andWhere(sprintf('%s.mailchimpId IS NULL OR %s.mailchimpSyncedAt IS NULL', $alias, $alias))
        ;
    }

    /** @return object[] */
    public function findMailchimpSyncNeeded(): array
    {
        return $this->createMailchimpSyncNeededQueryBuilder('o')
            ->andWhere('o.subscribedToNewsletter = :subscribed')
            ->setParameter('subscribed', true)
            ->getQuery()
            ->getResult()
        ;
    }

    /** @return object[] */
    public function findMailchimpSyncNeededSince(\DateTimeInterface $since): array
    {
        return $this->createMailchimpSyncNeededQueryBuilder('o')
            ->orWhere('o.updatedAt >= :since')
            ->andWhere('o.subscribedToNewsletter = :subscribed')
            ->setParameter('since', $since)
            ->setParameter('subscribed', true)
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * @param int[] $ids
     *
     * @return object[]
     */
    public function findMailchimpByIds(array $ids): array
    {
        return $this->createQueryBuilder('o')
            ->andWhere('o.id IN (:ids)')
            ->setParameter('ids', $ids)
            ->getQuery()
            ->getResult()
        ;
    }

    public function countMailchimpSyncedMembers(): int
    {
        return (int) $this->createQueryBuilder('o')
            ->select('COUNT(o)')
            ->andWhere('o.mailchimpId IS NOT NULL')
            ->andWhere('o.mailchimpError IS NULL')
            ->getQuery()
            ->getSingleScalarResult()
        ;
    }

    public function countMailchimpMembersWithError(): int
    {
        return (int) $this->createQueryBuilder('o')
            ->select('COUNT(o)')
            ->andWhere('o.mailchimpError IS NOT NULL')
            ->getQuery()
            ->getSingleScalarResult()
        ;
    }

    public function countMailchimpNeverSyncedMembers(): int
    {
        return (int) $this->createQueryBuilder('o')
            ->select('COUNT(o)')
            ->andWhere('o.mailchimpId IS NULL')
            ->getQuery()
            ->getSingleScalarResult()
        ;
    }
}
