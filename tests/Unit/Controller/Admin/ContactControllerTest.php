<?php

declare(strict_types=1);

namespace Tests\Webgriffe\SyliusMailchimpPlugin\Unit\Controller\Admin;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Response;
use Sylius\Component\Core\Model\CustomerInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Repository\CustomerRepositoryInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Twig\Environment;
use Webgriffe\SyliusMailchimpPlugin\Controller\Admin\ContactController;
use Webgriffe\SyliusMailchimpPlugin\Repository\MailchimpOrderRepositoryInterface;

final class ContactControllerTest extends TestCase
{
    private MockObject&CustomerRepositoryInterface $customerRepository;

    private MockObject&MailchimpOrderRepositoryInterface $orderRepository;

    private MockObject&Environment $twig;

    private ContactController $controller;

    protected function setUp(): void
    {
        $this->customerRepository = $this->createMock(CustomerRepositoryInterface::class);
        $this->orderRepository = $this->createMock(MailchimpOrderRepositoryInterface::class);
        $this->twig = $this->createMock(Environment::class);
        $this->controller = new ContactController(
            $this->customerRepository,
            $this->orderRepository,
            $this->twig,
        );
    }

    public function test_it_throws_not_found_when_customer_does_not_exist(): void
    {
        $this->customerRepository->method('find')->with(999)->willReturn(null);

        $this->expectException(NotFoundHttpException::class);

        $this->controller->showAction(999);
    }

    public function test_it_throws_not_found_when_find_returns_non_customer_object(): void
    {
        $this->customerRepository->method('find')->with(1)->willReturn(new \stdClass());

        $this->expectException(NotFoundHttpException::class);

        $this->controller->showAction(1);
    }

    public function test_it_loads_orders_and_carts_and_renders_for_found_customer(): void
    {
        $customer = $this->createMock(CustomerInterface::class);
        $order = $this->createMock(OrderInterface::class);
        $cart = $this->createMock(OrderInterface::class);

        $this->customerRepository->method('find')->with(42)->willReturn($customer);
        $this->orderRepository->expects($this->once())->method('findByCustomer')
            ->with($customer)
            ->willReturn([$order]);
        $this->orderRepository->expects($this->once())->method('findAbandonedCartsByCustomer')
            ->with($customer)
            ->willReturn([$cart]);
        $this->twig->expects($this->once())->method('render')
            ->with('@WebgriffeSyliusMailchimp/admin/contact/show.html.twig', [
                'customer' => $customer,
                'orders' => [$order],
                'carts' => [$cart],
            ])
            ->willReturn('<html>rendered</html>');

        $response = $this->controller->showAction(42);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame('<html>rendered</html>', $response->getContent());
    }
}
