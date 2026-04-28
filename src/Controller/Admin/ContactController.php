<?php

declare(strict_types=1);

namespace Webgriffe\SyliusMailchimpPlugin\Controller\Admin;

use Sylius\Component\Core\Model\CustomerInterface;
use Sylius\Component\Core\Repository\CustomerRepositoryInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Webgriffe\SyliusMailchimpPlugin\Repository\MailchimpOrderRepositoryInterface;

final class ContactController extends AbstractController
{
    public function __construct(
        private readonly CustomerRepositoryInterface $customerRepository,
        private readonly MailchimpOrderRepositoryInterface $orderRepository,
    ) {
    }

    public function showAction(int $id): Response
    {
        $customer = $this->customerRepository->find($id);

        if (!$customer instanceof CustomerInterface) {
            throw $this->createNotFoundException(sprintf('Customer with ID %d not found.', $id));
        }

        $orders = $this->orderRepository->findByCustomer($customer);
        $carts = $this->orderRepository->findAbandonedCartsByCustomer($customer);

        return $this->render('@WebgriffeSyliusMailchimp/admin/contact/show.html.twig', [
            'customer' => $customer,
            'orders' => $orders,
            'carts' => $carts,
        ]);
    }
}
