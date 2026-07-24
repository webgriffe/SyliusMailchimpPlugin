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
use Symfony\Component\Validator\Validation;
use Twig\Environment;
use Webgriffe\SyliusMailchimpPlugin\Client\Exception\ClientException;
use Webgriffe\SyliusMailchimpPlugin\Client\Exception\ComplianceStateException;
use Webgriffe\SyliusMailchimpPlugin\Controller\NewsletterController;
use Webgriffe\SyliusMailchimpPlugin\Exception\AudienceNotFoundException;
use Webgriffe\SyliusMailchimpPlugin\Form\Type\NewsletterSubscribeType;
use Webgriffe\SyliusMailchimpPlugin\Provider\AudienceContextInterface;
use Webgriffe\SyliusMailchimpPlugin\Updater\NewsletterSubscriberInterface;

final class NewsletterControllerTest extends TestCase
{
    private MockObject&NewsletterSubscriberInterface $newsletterSubscriber;

    private MockObject&AudienceContextInterface $audienceContext;

    private FormFactoryInterface $formFactory;

    private NewsletterController $controller;

    protected function setUp(): void
    {
        $this->newsletterSubscriber = $this->createMock(NewsletterSubscriberInterface::class);
        $this->audienceContext = $this->createMock(AudienceContextInterface::class);
        $this->formFactory = Forms::createFormFactoryBuilder()
            ->addExtension(new HttpFoundationExtension())
            ->addExtension(new ValidatorExtension(Validation::createValidator()))
            ->addType(new NewsletterSubscribeType())
            ->getFormFactory();
        $this->controller = new NewsletterController(
            $this->formFactory,
            $this->newsletterSubscriber,
            $this->audienceContext,
            new NullLogger(),
            $this->createMock(Environment::class),
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

    public function test_subscribes_synchronously_on_valid_form(): void
    {
        $this->audienceContext->method('getAudienceId')->willReturn('list-abc');

        $this->newsletterSubscriber
            ->expects($this->once())
            ->method('subscribe')
            ->with('user@example.com', 'list-abc');

        $request = Request::create('/newsletter/subscribe', 'POST', [
            'newsletter_subscribe' => ['email' => 'user@example.com'],
        ]);
        $request->headers->set('X-Requested-With', 'XMLHttpRequest');

        $response = $this->controller->subscribeAction($request);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('"success":true', (string) $response->getContent());
    }

    public function test_returns_error_response_when_subscriber_fails_with_compliance_state(): void
    {
        $this->audienceContext->method('getAudienceId')->willReturn('list-abc');

        $this->newsletterSubscriber
            ->method('subscribe')
            ->willThrowException(ComplianceStateException::forEmail('user@example.com', 'https://mailchimp.com/resubscribe'));

        $request = Request::create('/newsletter/subscribe', 'POST', [
            'newsletter_subscribe' => ['email' => 'user@example.com'],
        ]);
        $request->headers->set('X-Requested-With', 'XMLHttpRequest');

        $response = $this->controller->subscribeAction($request);

        $this->assertSame(422, $response->getStatusCode());
        $this->assertStringContainsString('"success":false', (string) $response->getContent());
        $this->assertStringContainsString('resubscribe', (string) $response->getContent());
    }

    public function test_returns_generic_error_response_when_subscriber_fails(): void
    {
        $this->audienceContext->method('getAudienceId')->willReturn('list-abc');

        $this->newsletterSubscriber
            ->method('subscribe')
            ->willThrowException(ClientException::fromResponse(500, 'boom'));

        $request = Request::create('/newsletter/subscribe', 'POST', [
            'newsletter_subscribe' => ['email' => 'user@example.com'],
        ]);
        $request->headers->set('X-Requested-With', 'XMLHttpRequest');

        $response = $this->controller->subscribeAction($request);

        $this->assertSame(500, $response->getStatusCode());
        $this->assertStringContainsString('"success":false', (string) $response->getContent());
        $this->assertStringNotContainsString('boom', (string) $response->getContent());
    }

    public function test_returns_check_email_message_when_mailchimp_rejects_with_a_client_error(): void
    {
        $this->audienceContext->method('getAudienceId')->willReturn('list-abc');

        $this->newsletterSubscriber
            ->method('subscribe')
            ->willThrowException(ClientException::fromResponse(400, 'invalid_resource'));

        $request = Request::create('/newsletter/subscribe', 'POST', [
            'newsletter_subscribe' => ['email' => 'user@example.con'],
        ]);
        $request->headers->set('X-Requested-With', 'XMLHttpRequest');

        $response = $this->controller->subscribeAction($request);

        $this->assertSame(422, $response->getStatusCode());
        $this->assertStringContainsString('"success":false', (string) $response->getContent());
        $this->assertStringContainsString('check the email address', (string) $response->getContent());
    }

    public function test_returns_generic_error_response_when_mailchimp_rate_limits(): void
    {
        $this->audienceContext->method('getAudienceId')->willReturn('list-abc');

        $this->newsletterSubscriber
            ->method('subscribe')
            ->willThrowException(ClientException::fromResponse(429, 'too many requests'));

        $request = Request::create('/newsletter/subscribe', 'POST', [
            'newsletter_subscribe' => ['email' => 'user@example.com'],
        ]);
        $request->headers->set('X-Requested-With', 'XMLHttpRequest');

        $response = $this->controller->subscribeAction($request);

        $this->assertSame(500, $response->getStatusCode());
        $this->assertStringNotContainsString('check the email address', (string) $response->getContent());
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
