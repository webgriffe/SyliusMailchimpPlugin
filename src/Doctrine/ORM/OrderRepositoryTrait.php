<?php

declare(strict_types=1);

namespace Webgriffe\SyliusMailchimpPlugin\Doctrine\ORM;

use Doctrine\ORM\QueryBuilder;
use Sylius\Component\Core\OrderCheckoutStates;
use Sylius\Component\Order\Model\OrderInterface;

trait OrderRepositoryTrait
{
    public function createMailchimpCartSyncableQueryBuilder(string $alias): QueryBuilder
    {
        return $this->createQueryBuilder($alias)
            ->andWhere(sprintf('%s.state = :cartState', $alias))
            ->setParameter('cartState', OrderInterface::STATE_CART)
        ;
    }

    public function createMailchimpCartSyncNeededQueryBuilder(string $alias): QueryBuilder
    {
        return $this->createMailchimpCartSyncableQueryBuilder($alias)
            ->andWhere(sprintf('%s.mailchimpCartId IS NULL', $alias))
        ;
    }

    public function createMailchimpOrderSyncNeededQueryBuilder(string $alias): QueryBuilder
    {
        return $this->createMailchimpOrderSyncableQueryBuilder($alias)
            ->andWhere(sprintf('%s.mailchimpOrderId IS NULL', $alias))
        ;
    }

    public function createMailchimpOrderSyncableQueryBuilder(string $alias): QueryBuilder
    {
        return $this->createQueryBuilder($alias)
            ->andWhere(sprintf('%s.checkoutState = :completedCheckoutState', $alias))
            ->setParameter('completedCheckoutState', OrderCheckoutStates::STATE_COMPLETED)
        ;
    }

    /** @return object[] */
    public function findMailchimpCartSyncNeeded(bool $includeFailed = false): array
    {
        $queryBuilder = $this->createMailchimpCartSyncNeededQueryBuilder('o');
        if (!$includeFailed) {
            $queryBuilder->andWhere('o.mailchimpCartError IS NULL');
        }

        return $queryBuilder->getQuery()->getResult();
    }

    /** @return object[] */
    public function findMailchimpOrderSyncNeeded(?\DateTimeInterface $updatedSince = null, bool $includeFailed = false): array
    {
        $queryBuilder = $this->createMailchimpOrderSyncNeededQueryBuilder('o');
        if (!$includeFailed) {
            $queryBuilder->andWhere('o.mailchimpOrderError IS NULL');
        }
        if ($updatedSince !== null) {
            $queryBuilder
                ->andWhere('o.updatedAt >= :since')
                ->setParameter('since', $updatedSince)
            ;
        }

        return $queryBuilder->getQuery()->getResult();
    }

    /** @return object[] */
    public function findMailchimpOrderSyncable(bool $includeFailed = false): array
    {
        $queryBuilder = $this->createMailchimpOrderSyncableQueryBuilder('o');
        if (!$includeFailed) {
            $queryBuilder->andWhere('o.mailchimpOrderError IS NULL');
        }

        return $queryBuilder->getQuery()->getResult();
    }

    /** @return object[] */
    public function findMailchimpCartSyncNeededUpdatedSince(\DateTimeInterface $since, bool $includeFailed = false): array
    {
        $queryBuilder = $this->createMailchimpCartSyncableQueryBuilder('o');
        if (!$includeFailed) {
            $queryBuilder->andWhere('o.mailchimpCartError IS NULL');
        }

        return $queryBuilder
            ->andWhere($queryBuilder->expr()->orX('o.mailchimpCartId IS NULL', 'o.updatedAt >= :since'))
            ->setParameter('since', $since)
            ->getQuery()
            ->getResult()
        ;
    }

    /** @return object[] */
    public function findMailchimpOrderSyncNeededUpdatedSince(\DateTimeInterface $since, bool $includeFailed = false): array
    {
        $queryBuilder = $this->createMailchimpOrderSyncableQueryBuilder('o');
        if (!$includeFailed) {
            $queryBuilder->andWhere('o.mailchimpOrderError IS NULL');
        }

        return $queryBuilder
            ->andWhere($queryBuilder->expr()->orX('o.mailchimpOrderId IS NULL', 'o.updatedAt >= :since'))
            ->setParameter('since', $since)
            ->getQuery()
            ->getResult()
        ;
    }

    public function countMailchimpPendingCarts(): int
    {
        return (int) $this->createMailchimpCartSyncNeededQueryBuilder('o')
            ->select('COUNT(o)')
            ->getQuery()
            ->getSingleScalarResult()
        ;
    }

    public function countMailchimpPendingOrders(): int
    {
        return (int) $this->createMailchimpOrderSyncNeededQueryBuilder('o')
            ->select('COUNT(o)')
            ->getQuery()
            ->getSingleScalarResult()
        ;
    }

    /** @return object[] */
    public function findAbandonedCartsByCustomer(\Sylius\Component\Core\Model\CustomerInterface $customer): array
    {
        return $this->createQueryBuilder('o')
            ->andWhere('o.customer = :customer')
            ->andWhere('o.state = :state')
            ->setParameter('customer', $customer)
            ->setParameter('state', OrderInterface::STATE_CART)
            ->orderBy('o.updatedAt', 'DESC')
            ->getQuery()
            ->getResult()
        ;
    }
}
