<?php

declare(strict_types=1);

namespace Webgriffe\SyliusMailchimpPlugin\Provider;

use Sylius\Component\Core\Model\ChannelInterface;

interface AudienceProviderInterface
{
    public function getAudienceId(ChannelInterface $channel): string;
}
