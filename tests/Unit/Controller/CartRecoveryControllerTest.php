<?php

declare(strict_types=1);

namespace Tests\Webgriffe\SyliusMailchimpPlugin\Unit\Controller;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Core\Repository\OrderRepositoryInterface;
use Sylius\Component\Core\Storage\CartStorageInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Tests\Webgriffe\SyliusMailchimpPlugin\Entity\Channel\Channel;
use Tests\Webgriffe\SyliusMailchimpPlugin\Entity\Order\Order;
use Webgriffe\SyliusMailchimpPlugin\Controller\CartRecoveryController;

final class CartRecoveryControllerTest extends TestCase
{
    private MockObject&OrderRepositoryInterface $orderRepository;

    private MockObject&CartStorageInterface $cartStorage;

    private MockObject&ChannelContextInterface $channelContext;

    private MockObject&UrlGeneratorInterface $urlGenerator;

    private CartRecoveryController $controller;

    protected function setUp(): void
    {
        $this->orderRepository = $this->createMock(OrderRepositoryInterface::class);
        $this->cartStorage = $this->createMock(CartStorageInterface::class);
        $this->channelContext = $this->createMock(ChannelContextInterface::class);
        $this->urlGenerator = $this->createMock(UrlGeneratorInterface::class);

        $this->controller = new CartRecoveryController(
            $this->orderRepository,
            $this->cartStorage,
            $this->channelContext,
            $this->urlGenerator,
        );
    }

    public function test_restores_cart_and_redirects_to_cart_summary(): void
    {
        $order = new Order();
        $channel = new Channel();

        $this->orderRepository->method('findCartByTokenValue')->with('abc123')->willReturn($order);
        $this->channelContext->method('getChannel')->willReturn($channel);
        $this->cartStorage->expects(self::once())->method('setForChannel')->with($channel, $order);
        $this->urlGenerator->method('generate')->with('sylius_shop_cart_summary')->willReturn('/cart');

        $response = $this->controller->restoreAction('abc123');

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame('/cart', $response->getTargetUrl());
    }

    public function test_redirects_to_homepage_when_order_not_found(): void
    {
        $this->orderRepository->method('findCartByTokenValue')->willReturn(null);
        $this->urlGenerator->method('generate')->with('sylius_shop_homepage')->willReturn('/');
        $this->cartStorage->expects(self::never())->method('setForChannel');

        $response = $this->controller->restoreAction('invalid-token');

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame('/', $response->getTargetUrl());
    }
}
