<?php

declare(strict_types=1);

namespace Webgriffe\SyliusMailchimpPlugin\Exception;

final class AudienceNotFoundException extends \RuntimeException
{
    public static function forChannel(string $channelCode): self
    {
        return new self(sprintf('No Mailchimp audience configured for channel "%s".', $channelCode));
    }
}
