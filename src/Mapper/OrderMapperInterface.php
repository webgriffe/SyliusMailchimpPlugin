<?php

declare(strict_types=1);

namespace Webgriffe\SyliusMailchimpPlugin\Mapper;

use Sylius\Component\Core\Model\OrderInterface;

interface OrderMapperInterface
{
    /** @return array<string, mixed> */
    public function map(OrderInterface $order, bool $isInRealTime = false): array;
}
