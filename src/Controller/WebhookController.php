<?php

declare(strict_types=1);

namespace Webgriffe\SyliusMailchimpPlugin\Controller;

use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Webgriffe\SyliusMailchimpPlugin\Message\Member\MemberSubscriptionUpdate;

final class WebhookController
{
    private const SIGNATURE_HEADER = 'X-Mailchimp-Signature';

    private const SIGNATURE_TIMESTAMP_TOLERANCE_SECONDS = 300;

    public function __construct(
        private readonly MessageBusInterface $messageBus,
        private readonly LoggerInterface $logger,
        private readonly ?string $webhookSecret,
        private readonly ?string $webhookSigningSecret = null,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        if ($this->webhookSecret !== null && $this->webhookSecret !== '') {
            $providedSecret = $request->query->get('secret', '');
            if ($providedSecret !== $this->webhookSecret) {
                $this->logger->warning('[Mailchimp] Webhook received with invalid secret.');

                return new Response('Forbidden', Response::HTTP_FORBIDDEN);
            }
        }

        // Mailchimp sends a GET request as a "health check" — just return 200
        if ($request->isMethod(Request::METHOD_GET)) {
            return new Response('OK');
        }

        if ($this->webhookSigningSecret !== null && $this->webhookSigningSecret !== '') {
            if (!$this->hasValidSignature($request, $this->webhookSigningSecret)) {
                $this->logger->warning('[Mailchimp] Webhook received with missing or invalid signature.');

                return new Response('Forbidden', Response::HTTP_FORBIDDEN);
            }
        }

        $type = (string) $request->request->get('type', '');

        // Mailchimp sends nested data[] parameters
        $data = $request->request->all('data');
        $listId = isset($data['list_id']) && is_string($data['list_id']) ? $data['list_id'] : '';
        $email = isset($data['email']) && is_string($data['email']) ? $data['email'] : '';

        if ($type === '' || $listId === '' || $email === '') {
            $this->logger->warning('[Mailchimp] Webhook payload missing required fields.', [
                'type' => $type,
                'list_id' => $listId,
                'email' => $email,
            ]);

            return new Response('Bad Request', Response::HTTP_BAD_REQUEST);
        }

        $this->logger->info('[Mailchimp] Webhook received: type={type}, list={list}, email={email}', [
            'type' => $type,
            'list' => $listId,
            'email' => $email,
        ]);

        $this->messageBus->dispatch(new MemberSubscriptionUpdate($type, $email, $listId));

        return new Response('OK');
    }

    /**
     * Mailchimp signs webhook deliveries with HMAC-SHA256 over "{timestamp}.{raw_body}" and sends
     * the result in the "X-Mailchimp-Signature" header as "t={timestamp},v1={hex_signature}".
     */
    private function hasValidSignature(Request $request, string $signingSecret): bool
    {
        $header = $request->headers->get(self::SIGNATURE_HEADER);
        if ($header === null || $header === '') {
            return false;
        }

        $timestamp = null;
        $signature = null;
        foreach (explode(',', $header) as $part) {
            [$key, $value] = array_pad(explode('=', $part, 2), 2, '');
            if ($key === 't') {
                $timestamp = $value;
            }
            if ($key === 'v1') {
                $signature = $value;
            }
        }

        if ($timestamp === null || $timestamp === '' || $signature === null || $signature === '') {
            return false;
        }

        if (abs(time() - (int) $timestamp) > self::SIGNATURE_TIMESTAMP_TOLERANCE_SECONDS) {
            return false;
        }

        $expectedSignature = hash_hmac('sha256', $timestamp . '.' . $request->getContent(), $signingSecret);

        return hash_equals($expectedSignature, $signature);
    }
}
