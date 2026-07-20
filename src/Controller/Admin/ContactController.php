<?php

declare(strict_types=1);

namespace Webgriffe\SyliusMailchimpPlugin\Controller\Admin;

use Sylius\Component\Core\Model\CustomerInterface;
use Sylius\Component\Core\Repository\CustomerRepositoryInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\FlashBagAwareSessionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Exception\InvalidCsrfTokenException;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Twig\Environment;
use Webgriffe\SyliusMailchimpPlugin\Enqueuer\MemberEnqueuerInterface;
use Webgriffe\SyliusMailchimpPlugin\Repository\MailchimpOrderRepositoryInterface;

final class ContactController
{
    public const SYNC_CSRF_TOKEN_ID = 'webgriffe_sylius_mailchimp_contact_sync';

    public function __construct(
        private readonly CustomerRepositoryInterface $customerRepository,
        private readonly MailchimpOrderRepositoryInterface $orderRepository,
        private readonly Environment $twig,
        private readonly MemberEnqueuerInterface $memberEnqueuer,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly CsrfTokenManagerInterface $csrfTokenManager,
    ) {
    }

    public function showAction(int $id): Response
    {
        $customer = $this->customerRepository->find($id);

        if (!$customer instanceof CustomerInterface) {
            throw new NotFoundHttpException(sprintf('Customer with ID %d not found.', $id));
        }

        $orders = $this->orderRepository->findByCustomer($customer);
        $carts = $this->orderRepository->findAbandonedCartsByCustomer($customer);

        return new Response($this->twig->render('@WebgriffeSyliusMailchimp/admin/contact/show.html.twig', [
            'customer' => $customer,
            'orders' => $orders,
            'carts' => $carts,
        ]));
    }

    public function syncAction(int $id, Request $request): Response
    {
        $customer = $this->customerRepository->find($id);

        if (!$customer instanceof CustomerInterface) {
            throw new NotFoundHttpException(sprintf('Customer with ID %d not found.', $id));
        }

        $token = new CsrfToken(self::SYNC_CSRF_TOKEN_ID, (string) $request->request->get('_csrf_token'));
        if (!$this->csrfTokenManager->isTokenValid($token)) {
            throw new InvalidCsrfTokenException('Invalid CSRF token.');
        }

        $this->memberEnqueuer->enqueue($customer);

        $session = $request->getSession();
        if ($session instanceof FlashBagAwareSessionInterface) {
            $session->getFlashBag()->add('success', 'webgriffe_sylius_mailchimp.ui.sync_queued');
        }

        return new RedirectResponse($this->urlGenerator->generate('webgriffe_sylius_mailchimp_contact_show', ['id' => $id]));
    }
}
