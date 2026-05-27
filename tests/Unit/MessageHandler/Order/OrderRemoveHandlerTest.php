<?php

declare(strict_types=1);

namespace Tests\Webgriffe\SyliusMailchimpPlugin\Unit\MessageHandler\Order;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Webgriffe\SyliusMailchimpPlugin\Client\MailchimpClientInterface;
use Webgriffe\SyliusMailchimpPlugin\Message\Order\OrderRemove;
use Webgriffe\SyliusMailchimpPlugin\MessageHandler\Order\OrderRemoveHandler;

final class OrderRemoveHandlerTest extends TestCase
{
    private MockObject&MailchimpClientInterface $mailchimpClient;

    protected function setUp(): void
    {
        $this->mailchimpClient = $this->createMock(MailchimpClientInterface::class);
    }

    public function test_order_remove_calls_remove_order(): void
    {
        $this->mailchimpClient->expects($this->once())->method('removeOrder')->with('WEB', 'order-42');

        $handler = new OrderRemoveHandler($this->mailchimpClient, new NullLogger());
        $handler(new OrderRemove('WEB', 'order-42'));
    }

    public function test_order_remove_rethrows_exception(): void
    {
        $this->mailchimpClient->method('removeOrder')->willThrowException(new \RuntimeException('API error'));

        $handler = new OrderRemoveHandler($this->mailchimpClient, new NullLogger());

        $this->expectException(\RuntimeException::class);
        $handler(new OrderRemove('WEB', 'order-42'));
    }
}
