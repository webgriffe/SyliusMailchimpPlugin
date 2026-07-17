<?php

declare(strict_types=1);

namespace Tests\Webgriffe\SyliusMailchimpPlugin\Unit\Util;

use PHPUnit\Framework\TestCase;
use Webgriffe\SyliusMailchimpPlugin\Client\Exception\ClientException;
use Webgriffe\SyliusMailchimpPlugin\Client\Exception\NotFoundException;
use Webgriffe\SyliusMailchimpPlugin\Exception\AudienceNotFoundException;
use Webgriffe\SyliusMailchimpPlugin\Exception\MissingCustomerEmailException;
use Webgriffe\SyliusMailchimpPlugin\Util\MailchimpErrorClassifier;

final class MailchimpErrorClassifierTest extends TestCase
{
    public function test_client_exception_with_4xx_status_is_permanent(): void
    {
        self::assertTrue(MailchimpErrorClassifier::isPermanent(ClientException::fromResponse(400, 'Invalid Resource')));
        self::assertTrue(MailchimpErrorClassifier::isPermanent(ClientException::fromResponse(422, 'Unprocessable')));
    }

    public function test_client_exception_with_5xx_status_is_transient(): void
    {
        self::assertFalse(MailchimpErrorClassifier::isPermanent(ClientException::fromResponse(500, 'Server error')));
        self::assertFalse(MailchimpErrorClassifier::isPermanent(ClientException::fromResponse(503, 'Unavailable')));
    }

    public function test_request_timeout_and_rate_limit_are_transient(): void
    {
        self::assertFalse(MailchimpErrorClassifier::isPermanent(ClientException::fromResponse(408, 'Timeout')));
        self::assertFalse(MailchimpErrorClassifier::isPermanent(ClientException::fromResponse(429, 'Too Many Requests')));
    }

    public function test_domain_exceptions_are_permanent(): void
    {
        self::assertTrue(MailchimpErrorClassifier::isPermanent(new AudienceNotFoundException('No audience')));
        self::assertTrue(MailchimpErrorClassifier::isPermanent(new MissingCustomerEmailException('No email')));
        self::assertTrue(MailchimpErrorClassifier::isPermanent(new NotFoundException('Not found')));
        self::assertTrue(MailchimpErrorClassifier::isPermanent(new \InvalidArgumentException('Bad argument')));
    }

    public function test_generic_exceptions_are_transient(): void
    {
        self::assertFalse(MailchimpErrorClassifier::isPermanent(new \RuntimeException('Network error')));
    }
}
