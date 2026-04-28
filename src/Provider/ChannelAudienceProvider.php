<?php

declare(strict_types=1);

namespace Webgriffe\SyliusMailchimpPlugin\Provider;

use Sylius\Component\Core\Model\ChannelInterface;
use Webgriffe\SyliusMailchimpPlugin\Exception\AudienceNotFoundException;
use Webgriffe\SyliusMailchimpPlugin\Model\ChannelMailchimpAwareInterface;

final class ChannelAudienceProvider implements AudienceProviderInterface
{
    #[\Override]
    public function getAudienceId(ChannelInterface $channel): string
    {
        if (!$channel instanceof ChannelMailchimpAwareInterface) {
            throw new AudienceNotFoundException(sprintf(
                'Channel "%s" does not implement %s.',
                (string) $channel->getCode(),
                ChannelMailchimpAwareInterface::class,
            ));
        }

        $audienceId = $channel->getMailchimpAudienceId();
        if ($audienceId === null || $audienceId === '') {
            throw new AudienceNotFoundException(sprintf(
                'No Mailchimp audience ID configured for channel "%s".',
                (string) $channel->getCode(),
            ));
        }

        return $audienceId;
    }
}
