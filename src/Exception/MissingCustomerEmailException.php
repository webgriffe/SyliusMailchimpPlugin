<?php

declare(strict_types=1);

namespace Webgriffe\SyliusMailchimpPlugin\Exception;

final class MissingCustomerEmailException extends \RuntimeException
{
    public static function forCustomerId(int $customerId): self
    {
        return new self(sprintf('Customer #%d has no email address.', $customerId));
    }
}
