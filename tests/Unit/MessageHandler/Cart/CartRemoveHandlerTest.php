<?php

declare(strict_types=1);

namespace Tests\Webgriffe\SyliusMailchimpPlugin\Unit\MessageHandler\Cart;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Webgriffe\SyliusMailchimpPlugin\Client\MailchimpClientInterface;
use Webgriffe\SyliusMailchimpPlugin\Message\Cart\CartRemove;
use Webgriffe\SyliusMailchimpPlugin\MessageHandler\Cart\CartRemoveHandler;

final class CartRemoveHandlerTest extends TestCase
{
    private MockObject&MailchimpClientInterface $mailchimpClient;

    protected function setUp(): void
    {
        $this->mailchimpClient = $this->createMock(MailchimpClientInterface::class);
    }

    public function test_cart_remove_calls_remove_cart(): void
    {
        $this->mailchimpClient->expects($this->once())->method('removeCart')->with('WEB', 'cart-abc');

        $handler = new CartRemoveHandler($this->mailchimpClient, new NullLogger());
        $handler(new CartRemove('WEB', 'cart-abc'));
    }

    public function test_cart_remove_rethrows_exception(): void
    {
        $this->mailchimpClient->method('removeCart')->willThrowException(new \RuntimeException('API error'));

        $handler = new CartRemoveHandler($this->mailchimpClient, new NullLogger());

        $this->expectException(\RuntimeException::class);
        $handler(new CartRemove('WEB', 'cart-abc'));
    }
}
