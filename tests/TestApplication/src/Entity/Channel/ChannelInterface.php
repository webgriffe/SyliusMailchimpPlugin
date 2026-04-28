<?php

declare(strict_types=1);

namespace Tests\Webgriffe\SyliusMailchimpPlugin\Entity\Channel;

use Sylius\Component\Core\Model\ChannelInterface as BaseChannelInterface;
use Webgriffe\SyliusMailchimpPlugin\Model\ChannelMailchimpAwareInterface;

interface ChannelInterface extends BaseChannelInterface, ChannelMailchimpAwareInterface
{
}
