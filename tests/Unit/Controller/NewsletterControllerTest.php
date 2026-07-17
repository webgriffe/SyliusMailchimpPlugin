<?php

declare(strict_types=1);

namespace Tests\Webgriffe\SyliusMailchimpPlugin\Unit\Controller;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Form\Extension\HttpFoundation\HttpFoundationExtension;
use Symfony\Component\Form\Extension\Validator\ValidatorExtension;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\Forms;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Validator\Validation;
use Webgriffe\SyliusMailchimpPlugin\Client\Exception\ClientException;
use Webgriffe\SyliusMailchimpPlugin\Client\Exception\ComplianceStateException;
use Webgriffe\SyliusMailchimpPlugin\Controller\NewsletterController;
use Webgriffe\SyliusMailchimpPlugin\Exception\AudienceNotFoundException;
use Webgriffe\SyliusMailchimpPlugin\Form\Type\NewsletterSubscribeType;
use Webgriffe\SyliusMailchimpPlugin\Message\Newsletter\NewsletterSubscribe;
use Webgriffe\SyliusMailchimpPlugin\Provider\AudienceContextInterface;

final class NewsletterControllerTest extends TestCase
{
    private MockObject&MessageBusInterface $messageBus;

    private MockObject&AudienceContextInterface $audienceContext;

    private FormFactoryInterface $formFactory;

    private NewsletterController $controller;

    protected function setUp(): void
    {
        $this->messageBus = $this->createMock(MessageBusInterface::class);
        $this->audienceContext = $this->createMock(AudienceContextInterface::class);
        $this->formFactory = Forms::createFormFactoryBuilder()
            ->addExtension(new HttpFoundationExtension())
            ->addExtension(new ValidatorExtension(Validation::createValidator()))
            ->addType(new NewsletterSubscribeType())
            ->getFormFactory();
        $this->controller = new NewsletterController(
            $this->formFactory,
            $this->messageBus,
            $this->audienceContext,
            new NullLogger(),
        );
    }

    public function test_returns_not_acceptable_for_non_xhr(): void
    {
        $request = Request::create('/newsletter/subscribe', 'POST');

        $response = $this->controller->subscribeAction($request);

        $this->assertSame(406, $response->getStatusCode());
    }

    public function test_returns_unprocessable_entity_when_form_not_submitted(): void
    {
        $request = Request::create('/newsletter/subscribe', 'POST');
        $request->headers->set('X-Requested-With', 'XMLHttpRequest');

        $response = $this->controller->subscribeAction($request);

        $this->assertSame(422, $response->getStatusCode());
    }

    public function test_dispatches_newsletter_subscribe_message_on_valid_form(): void
    {
        $this->audienceContext->method('getAudienceId')->willReturn('list-abc');

        $this->messageBus
            ->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(static function (NewsletterSubscribe $msg): bool {
                return $msg->email === 'user@example.com' && $msg->listId === 'list-abc';
            }))
            ->willReturn(new Envelope(new NewsletterSubscribe('user@example.com', 'list-abc')));

        $request = Request::create('/newsletter/subscribe', 'POST', [
            'newsletter_subscribe' => ['email' => 'user@example.com'],
        ]);
        $request->headers->set('X-Requested-With', 'XMLHttpRequest');

        $response = $this->controller->subscribeAction($request);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('"success":true', (string) $response->getContent());
    }

    public function test_returns_error_response_when_handler_fails_with_compliance_state(): void
    {
        $this->audienceContext->method('getAudienceId')->willReturn('list-abc');

        $message = new NewsletterSubscribe('user@example.com', 'list-abc');
        $this->messageBus->method('dispatch')->willThrowException(new HandlerFailedException(
            new Envelope($message),
            [ComplianceStateException::forEmail('user@example.com', 'https://mailchimp.com/resubscribe')],
        ));

        $request = Request::create('/newsletter/subscribe', 'POST', [
            'newsletter_subscribe' => ['email' => 'user@example.com'],
        ]);
        $request->headers->set('X-Requested-With', 'XMLHttpRequest');

        $response = $this->controller->subscribeAction($request);

        $this->assertSame(422, $response->getStatusCode());
        $this->assertStringContainsString('"success":false', (string) $response->getContent());
        $this->assertStringContainsString('resubscribe', (string) $response->getContent());
    }

    public function test_returns_generic_error_response_when_handler_fails(): void
    {
        $this->audienceContext->method('getAudienceId')->willReturn('list-abc');

        $message = new NewsletterSubscribe('user@example.com', 'list-abc');
        $this->messageBus->method('dispatch')->willThrowException(new HandlerFailedException(
            new Envelope($message),
            [ClientException::fromResponse(500, 'boom')],
        ));

        $request = Request::create('/newsletter/subscribe', 'POST', [
            'newsletter_subscribe' => ['email' => 'user@example.com'],
        ]);
        $request->headers->set('X-Requested-With', 'XMLHttpRequest');

        $response = $this->controller->subscribeAction($request);

        $this->assertSame(500, $response->getStatusCode());
        $this->assertStringContainsString('"success":false', (string) $response->getContent());
        $this->assertStringNotContainsString('boom', (string) $response->getContent());
    }

    public function test_returns_500_when_audience_not_found(): void
    {
        $this->audienceContext->method('getAudienceId')->willThrowException(new AudienceNotFoundException('No audience'));

        $request = Request::create('/newsletter/subscribe', 'POST', [
            'newsletter_subscribe' => ['email' => 'user@example.com'],
        ]);
        $request->headers->set('X-Requested-With', 'XMLHttpRequest');

        $response = $this->controller->subscribeAction($request);

        $this->assertSame(500, $response->getStatusCode());
    }
}
