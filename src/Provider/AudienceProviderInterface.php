<?php

declare(strict_types=1);

namespace Webgriffe\SyliusMailchimpPlugin\Provider;

use Sylius\Component\Core\Model\ChannelInterface;
use Webgriffe\SyliusMailchimpPlugin\ValueObject\Audience;

interface AudienceProviderInterface
{
    public function getAudience(ChannelInterface $channel, ?string $localeCode = null): Audience;
}
