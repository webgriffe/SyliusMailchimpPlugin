<?php

declare(strict_types=1);

namespace Webgriffe\SyliusMailchimpPlugin\Doctrine\ORM;

trait ProductRepositoryTrait
{
    /** @return object[] */
    public function findMailchimpSyncableByChannel(string $channelCode): array
    {
        return $this->createQueryBuilder('p')
            ->innerJoin('p.channels', 'c')
            ->andWhere('c.code = :channelCode')
            ->setParameter('channelCode', $channelCode)
            ->getQuery()
            ->getResult()
        ;
    }

    /** @return object[] */
    public function findMailchimpSyncableByChannelUpdatedSince(string $channelCode, \DateTimeInterface $since): array
    {
        return $this->createQueryBuilder('p')
            ->innerJoin('p.channels', 'c')
            ->andWhere('c.code = :channelCode')
            ->andWhere('p.updatedAt >= :since')
            ->setParameter('channelCode', $channelCode)
            ->setParameter('since', $since)
            ->getQuery()
            ->getResult()
        ;
    }

    /** @return object[] */
    public function findMailchimpSyncableUpdatedSince(\DateTimeInterface $since): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.updatedAt >= :since')
            ->setParameter('since', $since)
            ->getQuery()
            ->getResult()
        ;
    }
}
