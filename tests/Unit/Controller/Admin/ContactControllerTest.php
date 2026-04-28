<?php

declare(strict_types=1);

namespace Tests\Webgriffe\SyliusMailchimpPlugin\Unit\Controller\Admin;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\CustomerInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Repository\CustomerRepositoryInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Webgriffe\SyliusMailchimpPlugin\Controller\Admin\ContactController;
use Webgriffe\SyliusMailchimpPlugin\Repository\MailchimpOrderRepositoryInterface;

final class ContactControllerTest extends TestCase
{
    private MockObject&CustomerRepositoryInterface $customerRepository;

    private MockObject&MailchimpOrderRepositoryInterface $orderRepository;

    private ContactController $controller;

    protected function setUp(): void
    {
        $this->customerRepository = $this->createMock(CustomerRepositoryInterface::class);
        $this->orderRepository = $this->createMock(MailchimpOrderRepositoryInterface::class);
        $this->controller = new ContactController(
            $this->customerRepository,
            $this->orderRepository,
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

    public function test_it_loads_orders_and_carts_for_found_customer(): void
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

        // The controller requires a DI container for rendering — verify data loading logic
        // by confirming repositories are queried before the render call throws.
        try {
            $this->controller->showAction(42);
        } catch (\Error $e) {
            // Expected: AbstractController::render() requires a container (uninitialized property)
            $this->assertStringContainsString('container', $e->getMessage());
        }
    }
}
