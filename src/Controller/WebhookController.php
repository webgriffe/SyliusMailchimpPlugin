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
    public function __construct(
        private readonly MessageBusInterface $messageBus,
        private readonly LoggerInterface $logger,
        private readonly ?string $webhookSecret,
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
}
