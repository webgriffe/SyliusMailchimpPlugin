<?php

declare(strict_types=1);

namespace Tests\Webgriffe\SyliusMailchimpPlugin\Unit\Controller\Admin;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Repository\CustomerRepositoryInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Exception\InvalidCsrfTokenException;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Tests\Webgriffe\SyliusMailchimpPlugin\Entity\Customer\Customer;
use Tests\Webgriffe\SyliusMailchimpPlugin\Entity\Order\Order;
use Twig\Environment;
use Webgriffe\SyliusMailchimpPlugin\Controller\Admin\ContactController;
use Webgriffe\SyliusMailchimpPlugin\Enqueuer\MemberEnqueuerInterface;
use Webgriffe\SyliusMailchimpPlugin\Repository\MailchimpOrderRepositoryInterface;

final class ContactControllerTest extends TestCase
{
    private MockObject&CustomerRepositoryInterface $customerRepository;

    private MockObject&MailchimpOrderRepositoryInterface $orderRepository;

    private MockObject&Environment $twig;

    private MockObject&MemberEnqueuerInterface $memberEnqueuer;

    private MockObject&UrlGeneratorInterface $urlGenerator;

    private MockObject&CsrfTokenManagerInterface $csrfTokenManager;

    private ContactController $controller;

    protected function setUp(): void
    {
        $this->customerRepository = $this->createMock(CustomerRepositoryInterface::class);
        $this->orderRepository = $this->createMock(MailchimpOrderRepositoryInterface::class);
        $this->twig = $this->createMock(Environment::class);
        $this->memberEnqueuer = $this->createMock(MemberEnqueuerInterface::class);
        $this->urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $this->csrfTokenManager = $this->createMock(CsrfTokenManagerInterface::class);
        $this->controller = new ContactController(
            $this->customerRepository,
            $this->orderRepository,
            $this->twig,
            $this->memberEnqueuer,
            $this->urlGenerator,
            $this->csrfTokenManager,
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
        $customer = new Customer();
        $order = new Order();
        $cart = new Order();

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

    public function test_sync_action_throws_not_found_when_customer_does_not_exist(): void
    {
        $this->customerRepository->method('find')->with(999)->willReturn(null);

        $this->expectException(NotFoundHttpException::class);

        $this->controller->syncAction(999, new Request());
    }

    public function test_sync_action_throws_invalid_csrf_token_exception_when_token_is_invalid(): void
    {
        $customer = new Customer();
        $this->customerRepository->method('find')->with(42)->willReturn($customer);
        $this->csrfTokenManager->method('isTokenValid')
            ->with(new CsrfToken(ContactController::SYNC_CSRF_TOKEN_ID, 'invalid'))
            ->willReturn(false);

        $this->expectException(InvalidCsrfTokenException::class);

        $this->controller->syncAction(42, new Request([], ['_csrf_token' => 'invalid']));
    }

    public function test_sync_action_enqueues_customer_and_redirects_when_token_is_valid(): void
    {
        $customer = new Customer();
        $this->customerRepository->method('find')->with(42)->willReturn($customer);
        $this->csrfTokenManager->method('isTokenValid')
            ->with(new CsrfToken(ContactController::SYNC_CSRF_TOKEN_ID, 'valid'))
            ->willReturn(true);
        $this->memberEnqueuer->expects($this->once())->method('enqueue')->with($customer);
        $this->urlGenerator->method('generate')
            ->with('webgriffe_sylius_mailchimp_contact_show', ['id' => 42])
            ->willReturn('/admin/mailchimp/contacts/42');

        $request = new Request([], ['_csrf_token' => 'valid']);
        $request->setSession(new Session(new MockArraySessionStorage()));

        $response = $this->controller->syncAction(42, $request);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame('/admin/mailchimp/contacts/42', $response->getTargetUrl());
    }
}
