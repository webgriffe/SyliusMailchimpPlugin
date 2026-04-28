<?php

declare(strict_types=1);

namespace Webgriffe\SyliusMailchimpPlugin\Enqueuer;

use Sylius\Component\Core\Model\ChannelInterface;
use Webgriffe\SyliusMailchimpPlugin\Model\ChannelMailchimpAwareInterface;

interface StoreEnqueuerInterface
{
    public function enqueue(ChannelInterface&ChannelMailchimpAwareInterface $channel): void;
}
