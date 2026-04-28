<?php

declare(strict_types=1);

namespace Webgriffe\SyliusMailchimpPlugin\Repository;

use Sylius\Component\Core\Repository\ProductRepositoryInterface;

interface MailchimpProductRepositoryInterface extends ProductRepositoryInterface
{
    /** @return object[] */
    public function findMailchimpSyncableByChannel(string $channelCode): array;

    /** @return object[] */
    public function findMailchimpSyncableByChannelUpdatedSince(string $channelCode, \DateTimeInterface $since): array;

    /** @return object[] */
    public function findMailchimpSyncableUpdatedSince(\DateTimeInterface $since): array;
}
