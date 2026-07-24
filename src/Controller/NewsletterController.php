<?php

declare(strict_types=1);

namespace Webgriffe\SyliusMailchimpPlugin\Controller;

use Psr\Log\LoggerInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;
use Webgriffe\SyliusMailchimpPlugin\Client\Exception\ClientException;
use Webgriffe\SyliusMailchimpPlugin\Client\Exception\ComplianceStateException;
use Webgriffe\SyliusMailchimpPlugin\Exception\AudienceNotFoundException;
use Webgriffe\SyliusMailchimpPlugin\Form\Type\NewsletterSubscribeType;
use Webgriffe\SyliusMailchimpPlugin\Provider\AudienceContextInterface;
use Webgriffe\SyliusMailchimpPlugin\Updater\NewsletterSubscriberInterface;

final class NewsletterController
{
    public function __construct(
        private readonly FormFactoryInterface $formFactory,
        private readonly NewsletterSubscriberInterface $newsletterSubscriber,
        private readonly AudienceContextInterface $audienceContext,
        private readonly LoggerInterface $logger,
        private readonly Environment $twig,
    ) {
    }

    public function formAction(): Response
    {
        $form = $this->formFactory->create(NewsletterSubscribeType::class);

        return new Response($this->twig->render(
            '@WebgriffeSyliusMailchimpPlugin/shop/newsletter/subscribe_form.html.twig',
            ['form' => $form->createView()],
        ));
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
            $this->newsletterSubscriber->subscribe($email, $listId);
        } catch (ComplianceStateException $e) {
            $errorMessage = $e->getMessage();
            $resubscribeUrl = $e->getResubscribeUrl();
            if ($resubscribeUrl !== null) {
                $errorMessage .= sprintf(' You can resubscribe at %s', $resubscribeUrl);
            }

            return new JsonResponse(['success' => false, 'errors' => [$errorMessage]], Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (ClientException $e) {
            if ($e->getStatusCode() >= 400 && $e->getStatusCode() < 500 && $e->getStatusCode() !== 429) {
                return new JsonResponse(['success' => false, 'errors' => ['Please check the email address you entered.']], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            return new JsonResponse(['success' => false, 'errors' => ['Subscription is temporarily unavailable.']], Response::HTTP_INTERNAL_SERVER_ERROR);
        } catch (\Throwable) {
            return new JsonResponse(['success' => false, 'errors' => ['Subscription is temporarily unavailable.']], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return new JsonResponse(['success' => true]);
    }
}
