<?php

declare(strict_types=1);

namespace Webgriffe\SyliusMailchimpPlugin\Doctrine\ORM;

use Doctrine\ORM\QueryBuilder;

trait OrderRepositoryTrait
{
    public function createMailchimpCartSyncNeededQueryBuilder(string $alias): QueryBuilder
    {
        return $this->createQueryBuilder($alias)
            ->andWhere(sprintf('%s.mailchimpCartId IS NULL', $alias))
        ;
    }

    public function createMailchimpOrderSyncNeededQueryBuilder(string $alias): QueryBuilder
    {
        return $this->createQueryBuilder($alias)
            ->andWhere(sprintf('%s.mailchimpOrderId IS NULL', $alias))
        ;
    }

    /** @return object[] */
    public function findMailchimpCartSyncNeeded(): array
    {
        return $this->createMailchimpCartSyncNeededQueryBuilder('o')->getQuery()->getResult();
    }

    /** @return object[] */
    public function findMailchimpOrderSyncNeeded(): array
    {
        return $this->createMailchimpOrderSyncNeededQueryBuilder('o')->getQuery()->getResult();
    }

    /** @return object[] */
    public function findMailchimpCartSyncNeededUpdatedSince(\DateTimeInterface $since): array
    {
        return $this->createMailchimpCartSyncNeededQueryBuilder('o')
            ->orWhere('o.updatedAt >= :since')
            ->setParameter('since', $since)
            ->getQuery()
            ->getResult()
        ;
    }

    /** @return object[] */
    public function findMailchimpOrderSyncNeededUpdatedSince(\DateTimeInterface $since): array
    {
        return $this->createMailchimpOrderSyncNeededQueryBuilder('o')
            ->orWhere('o.updatedAt >= :since')
            ->setParameter('since', $since)
            ->getQuery()
            ->getResult()
        ;
    }

    public function countMailchimpPendingCarts(): int
    {
        return (int) $this->createQueryBuilder('o')
            ->select('COUNT(o)')
            ->andWhere('o.mailchimpCartId IS NULL')
            ->getQuery()
            ->getSingleScalarResult()
        ;
    }

    public function countMailchimpPendingOrders(): int
    {
        return (int) $this->createQueryBuilder('o')
            ->select('COUNT(o)')
            ->andWhere('o.mailchimpOrderId IS NULL')
            ->getQuery()
            ->getSingleScalarResult()
        ;
    }
}
