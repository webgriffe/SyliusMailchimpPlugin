<?php

declare(strict_types=1);

namespace Webgriffe\SyliusMailchimpPlugin\Util;

use Webgriffe\SyliusMailchimpPlugin\Client\Exception\ClientException;
use Webgriffe\SyliusMailchimpPlugin\Client\Exception\NotFoundException;
use Webgriffe\SyliusMailchimpPlugin\Exception\AudienceNotFoundException;
use Webgriffe\SyliusMailchimpPlugin\Exception\MissingCustomerEmailException;

final class MailchimpErrorClassifier
{
    /**
     * A permanent error will fail again on retry (invalid payload, missing configuration),
     * so handlers should record it instead of rethrowing; transient errors (network issues,
     * rate limits, Mailchimp 5xx) should be rethrown to let Messenger retry the message.
     */
    public static function isPermanent(\Throwable $e): bool
    {
        if ($e instanceof AudienceNotFoundException ||
            $e instanceof MissingCustomerEmailException ||
            $e instanceof NotFoundException ||
            $e instanceof \InvalidArgumentException) {
            return true;
        }

        if ($e instanceof ClientException) {
            $statusCode = $e->getStatusCode();

            return $statusCode >= 400 && $statusCode < 500 && $statusCode !== 408 && $statusCode !== 429;
        }

        return false;
    }
}
