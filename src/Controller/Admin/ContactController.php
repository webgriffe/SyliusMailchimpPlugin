<?php

declare(strict_types=1);

namespace Webgriffe\SyliusMailchimpPlugin\Controller\Admin;

use Sylius\Component\Core\Model\CustomerInterface;
use Sylius\Component\Core\Repository\CustomerRepositoryInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Twig\Environment;
use Webgriffe\SyliusMailchimpPlugin\Repository\MailchimpOrderRepositoryInterface;

final class ContactController
{
    public function __construct(
        private readonly CustomerRepositoryInterface $customerRepository,
        private readonly MailchimpOrderRepositoryInterface $orderRepository,
        private readonly Environment $twig,
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
}
