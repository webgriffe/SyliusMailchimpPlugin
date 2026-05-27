<?php

declare(strict_types=1);

namespace Tests\Webgriffe\SyliusMailchimpPlugin\Unit\MessageHandler\Store;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Webgriffe\SyliusMailchimpPlugin\Client\MailchimpClientInterface;
use Webgriffe\SyliusMailchimpPlugin\Message\Store\StoreRemove;
use Webgriffe\SyliusMailchimpPlugin\MessageHandler\Store\StoreRemoveHandler;

final class StoreRemoveHandlerTest extends TestCase
{
    private MockObject&MailchimpClientInterface $mailchimpClient;

    protected function setUp(): void
    {
        $this->mailchimpClient = $this->createMock(MailchimpClientInterface::class);
    }

    public function test_store_remove_calls_remove_store(): void
    {
        $this->mailchimpClient->expects(self::once())->method('removeStore')->with('web-store');

        $handler = new StoreRemoveHandler($this->mailchimpClient, new NullLogger());
        $handler(new StoreRemove('web-store'));
    }

    public function test_store_remove_rethrows_exception(): void
    {
        $this->mailchimpClient->method('removeStore')->willThrowException(new \RuntimeException('API error'));

        $handler = new StoreRemoveHandler($this->mailchimpClient, new NullLogger());

        $this->expectException(\RuntimeException::class);
        $handler(new StoreRemove('web-store'));
    }
}
