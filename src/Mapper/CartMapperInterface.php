<?php

declare(strict_types=1);

namespace Webgriffe\SyliusMailchimpPlugin\Mapper;

use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Webgriffe\SyliusMailchimpPlugin\Model\ChannelMailchimpAwareInterface;

interface CartMapperInterface
{
    /** @return array<string, mixed> */
    public function map(OrderInterface $order, ChannelInterface&ChannelMailchimpAwareInterface $channel): array;
}
