<?php

declare(strict_types=1);

namespace Tests\Webgriffe\SyliusMailchimpPlugin\Unit\MessageHandler;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Webgriffe\SyliusMailchimpPlugin\Client\Exception\ClientException;
use Webgriffe\SyliusMailchimpPlugin\Client\MailchimpClientInterface;
use Webgriffe\SyliusMailchimpPlugin\Message\Member\MemberRemove;
use Webgriffe\SyliusMailchimpPlugin\MessageHandler\Member\MemberRemoveHandler;

final class MemberRemoveHandlerTest extends TestCase
{
    private MockObject&MailchimpClientInterface $mailchimpClient;

    private MemberRemoveHandler $handler;

    protected function setUp(): void
    {
        $this->mailchimpClient = $this->createMock(MailchimpClientInterface::class);
        $this->handler = new MemberRemoveHandler($this->mailchimpClient, new NullLogger());
    }

    public function test_removes_member_from_mailchimp(): void
    {
        $this->mailchimpClient->expects($this->once())
            ->method('removeMember')
            ->with('list-id', 'hash123');

        ($this->handler)(new MemberRemove(1, 'list-id', 'hash123'));
    }

    public function test_rethrows_exception_on_client_error(): void
    {
        $this->expectException(ClientException::class);

        $this->mailchimpClient->method('removeMember')
            ->willThrowException(ClientException::fromResponse(500, 'Server error'));

        ($this->handler)(new MemberRemove(1, 'list-id', 'hash123'));
    }
}
