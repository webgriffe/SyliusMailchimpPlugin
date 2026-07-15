<?php

declare(strict_types=1);

namespace Tests\Webgriffe\SyliusMailchimpPlugin\Unit\Controller;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Webgriffe\SyliusMailchimpPlugin\Controller\WebhookController;
use Webgriffe\SyliusMailchimpPlugin\Message\Member\MemberSubscriptionUpdate;

final class WebhookControllerTest extends TestCase
{
    private MockObject&MessageBusInterface $messageBus;

    private WebhookController $controller;

    protected function setUp(): void
    {
        $this->messageBus = $this->createMock(MessageBusInterface::class);
        $this->controller = new WebhookController(
            $this->messageBus,
            new NullLogger(),
            'secret123',
        );
    }

    public function test_returns_ok_on_get_health_check(): void
    {
        $request = Request::create('/mailchimp/webhook?secret=secret123', 'GET');

        $response = ($this->controller)($request);

        $this->assertSame(200, $response->getStatusCode());
        $this->messageBus->expects($this->never())->method('dispatch');
    }

    public function test_returns_forbidden_on_invalid_secret(): void
    {
        $request = Request::create('/mailchimp/webhook?secret=wrong', 'POST');

        $response = ($this->controller)($request);

        $this->assertSame(403, $response->getStatusCode());
    }

    public function test_dispatches_message_on_valid_post(): void
    {
        $request = Request::create('/mailchimp/webhook?secret=secret123', 'POST', [
            'type' => 'subscribe',
            'data' => [
                'list_id' => 'list-abc',
                'email' => 'user@example.com',
            ],
        ]);

        $dispatched = null;
        $this->messageBus->expects($this->once())->method('dispatch')
            ->willReturnCallback(function (object $msg) use (&$dispatched): Envelope {
                $dispatched = $msg;

                return new Envelope($msg);
            });

        $response = ($this->controller)($request);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertInstanceOf(MemberSubscriptionUpdate::class, $dispatched);
        $this->assertSame('subscribe', $dispatched->type);
        $this->assertSame('list-abc', $dispatched->listId);
        $this->assertSame('user@example.com', $dispatched->email);
    }

    public function test_returns_bad_request_when_payload_incomplete(): void
    {
        $request = Request::create('/mailchimp/webhook?secret=secret123', 'POST', [
            'type' => 'subscribe',
            // missing data
        ]);

        $this->messageBus->expects($this->never())->method('dispatch');

        $response = ($this->controller)($request);

        $this->assertSame(400, $response->getStatusCode());
    }

    public function test_returns_forbidden_when_signing_secret_configured_and_signature_header_missing(): void
    {
        $controller = $this->createControllerWithSigningSecret('signing-key');
        $request = $this->createSignedRequest(null);

        $this->messageBus->expects($this->never())->method('dispatch');

        $response = ($controller)($request);

        $this->assertSame(403, $response->getStatusCode());
    }

    public function test_returns_forbidden_when_signature_is_invalid(): void
    {
        $controller = $this->createControllerWithSigningSecret('signing-key');
        $request = $this->createSignedRequest('t=' . time() . ',v1=' . str_repeat('0', 64));

        $this->messageBus->expects($this->never())->method('dispatch');

        $response = ($controller)($request);

        $this->assertSame(403, $response->getStatusCode());
    }

    public function test_returns_forbidden_when_signature_timestamp_is_too_old(): void
    {
        $timestamp = time() - 600;
        $body = 'type=subscribe&data%5Blist_id%5D=list-abc&data%5Bemail%5D=user%40example.com';
        $signature = 't=' . $timestamp . ',v1=' . hash_hmac('sha256', $timestamp . '.' . $body, 'signing-key');
        $controller = $this->createControllerWithSigningSecret('signing-key');
        $request = $this->createSignedRequest($signature, $body);

        $this->messageBus->expects($this->never())->method('dispatch');

        $response = ($controller)($request);

        $this->assertSame(403, $response->getStatusCode());
    }

    public function test_dispatches_message_when_signature_is_valid(): void
    {
        $timestamp = time();
        $body = 'type=subscribe&data%5Blist_id%5D=list-abc&data%5Bemail%5D=user%40example.com';
        $signature = 't=' . $timestamp . ',v1=' . hash_hmac('sha256', $timestamp . '.' . $body, 'signing-key');
        $controller = $this->createControllerWithSigningSecret('signing-key');
        $request = $this->createSignedRequest($signature, $body);

        $this->messageBus->expects($this->once())->method('dispatch')->willReturnCallback(
            static fn (object $msg) => new Envelope($msg),
        );

        $response = ($controller)($request);

        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_get_health_check_skips_signature_validation(): void
    {
        $controller = $this->createControllerWithSigningSecret('signing-key');
        $request = Request::create('/mailchimp/webhook?secret=secret123', 'GET');

        $response = ($controller)($request);

        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_no_secret_check_when_secret_is_null(): void
    {
        $controller = new WebhookController($this->messageBus, new NullLogger(), null);
        $request = Request::create('/mailchimp/webhook', 'POST', [
            'type' => 'unsubscribe',
            'data' => [
                'list_id' => 'list-xyz',
                'email' => 'user@example.com',
            ],
        ]);

        $this->messageBus->expects($this->once())->method('dispatch')->willReturnCallback(
            static fn (object $msg) => new Envelope($msg),
        );

        $response = ($controller)($request);

        $this->assertSame(200, $response->getStatusCode());
    }

    private function createControllerWithSigningSecret(string $signingSecret): WebhookController
    {
        return new WebhookController(
            $this->messageBus,
            new NullLogger(),
            'secret123',
            $signingSecret,
        );
    }

    private function createSignedRequest(
        ?string $signatureHeader,
        string $body = 'type=subscribe&data%5Blist_id%5D=list-abc&data%5Bemail%5D=user%40example.com',
    ): Request {
        $server = ['CONTENT_TYPE' => 'application/x-www-form-urlencoded'];
        if ($signatureHeader !== null) {
            $server['HTTP_X_MAILCHIMP_SIGNATURE'] = $signatureHeader;
        }

        parse_str($body, $parameters);

        /** @var array<string, mixed> $parameters */
        return Request::create('/mailchimp/webhook?secret=secret123', 'POST', $parameters, [], [], $server, $body);
    }
}
