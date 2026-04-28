<?php

declare(strict_types=1);

namespace Webgriffe\SyliusMailchimpPlugin\Provider;

use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Core\Model\ChannelInterface;

final class AudienceContext
{
    public function __construct(
        private readonly ChannelContextInterface $channelContext,
        private readonly AudienceProviderInterface $audienceProvider,
    ) {
    }

    public function getAudienceId(): string
    {
        /** @var ChannelInterface $channel */
        $channel = $this->channelContext->getChannel();

        return $this->audienceProvider->getAudienceId($channel);
    }
}
