<?php

declare(strict_types=1);

namespace Tests\Webgriffe\SyliusMailchimpPlugin\Unit\MessageHandler\Product;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Webgriffe\SyliusMailchimpPlugin\Client\MailchimpClientInterface;
use Webgriffe\SyliusMailchimpPlugin\Message\Product\ProductRemove;
use Webgriffe\SyliusMailchimpPlugin\MessageHandler\Product\ProductRemoveHandler;

final class ProductRemoveHandlerTest extends TestCase
{
    private MockObject&MailchimpClientInterface $mailchimpClient;

    protected function setUp(): void
    {
        $this->mailchimpClient = $this->createMock(MailchimpClientInterface::class);
    }

    public function test_product_remove_calls_remove_product(): void
    {
        $this->mailchimpClient->expects($this->once())->method('removeProduct')->with('WEB', 'TSHIRT');

        $handler = new ProductRemoveHandler($this->mailchimpClient, new NullLogger());
        $handler(new ProductRemove('WEB', 'TSHIRT'));
    }

    public function test_product_remove_rethrows_exception(): void
    {
        $this->mailchimpClient->method('removeProduct')->willThrowException(new \RuntimeException('API error'));

        $handler = new ProductRemoveHandler($this->mailchimpClient, new NullLogger());

        $this->expectException(\RuntimeException::class);
        $handler(new ProductRemove('WEB', 'TSHIRT'));
    }
}
