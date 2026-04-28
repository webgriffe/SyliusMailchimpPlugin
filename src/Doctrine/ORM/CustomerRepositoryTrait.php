<?php

declare(strict_types=1);

namespace Webgriffe\SyliusMailchimpPlugin\Doctrine\ORM;

use Doctrine\ORM\QueryBuilder;

trait CustomerRepositoryTrait
{
    public function createMailchimpSyncNeededQueryBuilder(string $alias): QueryBuilder
    {
        return $this->createQueryBuilder($alias)
            ->andWhere(sprintf('%s.mailchimpId IS NULL OR %s.mailchimpSyncedAt IS NULL', $alias, $alias))
        ;
    }

    /** @return object[] */
    public function findMailchimpSyncNeeded(): array
    {
        return $this->createMailchimpSyncNeededQueryBuilder('o')->getQuery()->getResult();
    }

    /** @return object[] */
    public function findMailchimpSyncNeededSince(\DateTimeInterface $since): array
    {
        return $this->createMailchimpSyncNeededQueryBuilder('o')
            ->orWhere('o.updatedAt >= :since')
            ->setParameter('since', $since)
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
}
