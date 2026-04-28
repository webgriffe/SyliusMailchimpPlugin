<?php

declare(strict_types=1);

namespace Webgriffe\SyliusMailchimpPlugin\Client\Exception;

final class NotFoundException extends \RuntimeException
{
    public static function forResource(string $resourceType, string $resourceId): self
    {
        return new self(sprintf('Mailchimp resource "%s" with id "%s" was not found.', $resourceType, $resourceId));
    }
}
