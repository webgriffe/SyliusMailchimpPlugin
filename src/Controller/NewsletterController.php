<?php

declare(strict_types=1);

namespace Webgriffe\SyliusMailchimpPlugin\Controller;

use Psr\Log\LoggerInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\MessageBusInterface;
use Webgriffe\SyliusMailchimpPlugin\Client\Exception\ComplianceStateException;
use Webgriffe\SyliusMailchimpPlugin\Exception\AudienceNotFoundException;
use Webgriffe\SyliusMailchimpPlugin\Form\Type\NewsletterSubscribeType;
use Webgriffe\SyliusMailchimpPlugin\Message\Newsletter\NewsletterSubscribe;
use Webgriffe\SyliusMailchimpPlugin\Provider\AudienceContextInterface;

final class NewsletterController
{
    public function __construct(
        private readonly FormFactoryInterface $formFactory,
        private readonly MessageBusInterface $messageBus,
        private readonly AudienceContextInterface $audienceContext,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function subscribeAction(Request $request): Response
    {
        if (!$request->isXmlHttpRequest()) {
            return new Response('Not Acceptable', Response::HTTP_NOT_ACCEPTABLE);
        }

        $form = $this->formFactory->create(NewsletterSubscribeType::class);
        $form->handleRequest($request);

        if (!$form->isSubmitted() || !$form->isValid()) {
            $errors = [];
            foreach ($form->getErrors(true) as $error) {
                /** @phpstan-ignore method.notFound */
                $errors[] = $error->getMessage();
            }

            return new JsonResponse(['success' => false, 'errors' => $errors], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        /** @var array{email: string} $data */
        $data = $form->getData();
        $email = $data['email'];

        try {
            $listId = $this->audienceContext->getAudienceId();
        } catch (AudienceNotFoundException $e) {
            $this->logger->warning('[Mailchimp] Newsletter subscribe: audience not found. {msg}', ['msg' => $e->getMessage()]);

            return new JsonResponse(['success' => false, 'errors' => ['Audience not configured.']], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        try {
            $this->messageBus->dispatch(new NewsletterSubscribe($email, $listId));
        } catch (HandlerFailedException $e) {
            foreach ($e->getWrappedExceptions() as $wrappedException) {
                if ($wrappedException instanceof ComplianceStateException) {
                    $this->logger->warning('[Mailchimp] Newsletter subscribe: compliance state for {email}: {msg}', ['email' => $email, 'msg' => $wrappedException->getMessage()]);

                    $errorMessage = $wrappedException->getMessage();
                    $resubscribeUrl = $wrappedException->getResubscribeUrl();
                    if ($resubscribeUrl !== null) {
                        $errorMessage .= sprintf(' You can resubscribe at %s', $resubscribeUrl);
                    }

                    return new JsonResponse(['success' => false, 'errors' => [$errorMessage]], Response::HTTP_UNPROCESSABLE_ENTITY);
                }
            }

            $this->logger->error('[Mailchimp] Newsletter subscribe failed for {email}: {msg}', ['email' => $email, 'msg' => $e->getMessage()]);

            return new JsonResponse(['success' => false, 'errors' => ['Subscription is temporarily unavailable.']], Response::HTTP_INTERNAL_SERVER_ERROR);
        } catch (\Throwable $e) {
            $this->logger->error('[Mailchimp] Newsletter subscribe failed for {email}: {msg}', ['email' => $email, 'msg' => $e->getMessage()]);

            return new JsonResponse(['success' => false, 'errors' => ['Subscription is temporarily unavailable.']], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $this->logger->info('[Mailchimp] Newsletter subscribe: dispatched NewsletterSubscribe for {email}.', ['email' => $email]);

        return new JsonResponse(['success' => true]);
    }
}
