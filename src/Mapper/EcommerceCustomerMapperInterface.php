<?php

declare(strict_types=1);

namespace Webgriffe\SyliusMailchimpPlugin\Mapper;

use Sylius\Component\Core\Model\OrderInterface;

interface EcommerceCustomerMapperInterface
{
    /** @return array<string, mixed> */
    public function mapFromOrder(OrderInterface $order): array;
}
