<?php

declare(strict_types=1);

namespace Tests\Webgriffe\SyliusMailchimpPlugin\Unit\MessageHandler\Cart;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Sylius\Component\Channel\Repository\ChannelRepositoryInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\CustomerInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Repository\OrderRepositoryInterface;
use Webgriffe\SyliusMailchimpPlugin\Client\MailchimpClientInterface;
use Webgriffe\SyliusMailchimpPlugin\Mapper\CartMapper;
use Webgriffe\SyliusMailchimpPlugin\Mapper\EcommerceCustomerMapper;
use Webgriffe\SyliusMailchimpPlugin\Mapper\StoreMapper;
use Webgriffe\SyliusMailchimpPlugin\Message\Cart\CartCreate;
use Webgriffe\SyliusMailchimpPlugin\Message\Cart\CartRemove;
use Webgriffe\SyliusMailchimpPlugin\Message\Cart\CartUpdate;
use Webgriffe\SyliusMailchimpPlugin\MessageHandler\Cart\CartCreateHandler;
use Webgriffe\SyliusMailchimpPlugin\MessageHandler\Cart\CartRemoveHandler;
use Webgriffe\SyliusMailchimpPlugin\MessageHandler\Cart\CartUpdateHandler;
use Webgriffe\SyliusMailchimpPlugin\Model\ChannelMailchimpAwareInterface;
use Webgriffe\SyliusMailchimpPlugin\Model\MailchimpOrderAwareInterface;

final class CartHandlersTest extends TestCase
{
    private OrderRepositoryInterface $orderRepository;

    private ChannelRepositoryInterface $channelRepository;

    private MailchimpClientInterface $mailchimpClient;

    private EntityManagerInterface $entityManager;

    private CartMapper $cartMapper;

    private StoreMapper $storeMapper;

    protected function setUp(): void
    {
        $this->orderRepository = $this->createMock(OrderRepositoryInterface::class);
        $this->channelRepository = $this->createMock(ChannelRepositoryInterface::class);
        $this->mailchimpClient = $this->createMock(MailchimpClientInterface::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->cartMapper = new CartMapper(new EcommerceCustomerMapper());
        $this->storeMapper = new StoreMapper();
    }

    public function testCartCreateCallsUpsertCartAndPersistsId(): void
    {
        $order = $this->createOrderMock(orderId: 5, tokenValue: 'cart-token-xyz');
        $channel = $this->createChannelMock('WEB');
        $this->orderRepository->method('find')->with(5)->willReturn($order);
        $this->channelRepository->method('find')->with(1)->willReturn($channel);
        $this->mailchimpClient->expects($this->once())->method('upsertCart');
        $order->expects($this->once())->method('setMailchimpCartId')->with('cart-token-xyz');
        $order->expects($this->once())->method('setMailchimpCartError')->with(null);
        $this->entityManager->expects($this->once())->method('flush');

        $handler = new CartCreateHandler(
            $this->orderRepository,
            $this->channelRepository,
            $this->cartMapper,
            $this->storeMapper,
            $this->mailchimpClient,
            $this->entityManager,
            new NullLogger(),
        );
        $handler(new CartCreate(5, 1));
    }

    public function testCartCreateSkipsWhenOrderNotFound(): void
    {
        $this->orderRepository->method('find')->willReturn(null);
        $this->mailchimpClient->expects($this->never())->method('upsertCart');

        $handler = new CartCreateHandler(
            $this->orderRepository,
            $this->channelRepository,
            $this->cartMapper,
            $this->storeMapper,
            $this->mailchimpClient,
            $this->entityManager,
            new NullLogger(),
        );
        $handler(new CartCreate(999, 1));
    }

    public function testCartUpdateCallsUpsertCart(): void
    {
        $order = $this->createOrderMock(orderId: 5, tokenValue: 'cart-token');
        $channel = $this->createChannelMock('WEB');
        $this->orderRepository->method('find')->willReturn($order);
        $this->channelRepository->method('find')->willReturn($channel);
        $this->mailchimpClient->expects($this->once())->method('upsertCart');

        $handler = new CartUpdateHandler(
            $this->orderRepository,
            $this->channelRepository,
            $this->cartMapper,
            $this->storeMapper,
            $this->mailchimpClient,
            $this->entityManager,
            new NullLogger(),
        );
        $handler(new CartUpdate(5, 1));
    }

    public function testCartRemoveCallsRemoveCart(): void
    {
        $this->mailchimpClient->expects($this->once())->method('removeCart')->with('WEB', 'cart-abc');

        $handler = new CartRemoveHandler($this->mailchimpClient, new NullLogger());
        $handler(new CartRemove('WEB', 'cart-abc'));
    }

    public function testCartRemoveRethrowsException(): void
    {
        $this->mailchimpClient->method('removeCart')->willThrowException(new \RuntimeException('API error'));

        $handler = new CartRemoveHandler($this->mailchimpClient, new NullLogger());

        $this->expectException(\RuntimeException::class);
        $handler(new CartRemove('WEB', 'cart-abc'));
    }

    /** @return OrderInterface&MailchimpOrderAwareInterface */
    private function createOrderMock(int $orderId, string $tokenValue): OrderInterface&MailchimpOrderAwareInterface
    {
        $customer = $this->createMock(CustomerInterface::class);
        $customer->method('getId')->willReturn(1);
        $customer->method('getEmail')->willReturn('x@example.com');
        $customer->method('getFirstName')->willReturn('');
        $customer->method('getLastName')->willReturn('');
        $customer->method('isSubscribedToNewsletter')->willReturn(false);

        /** @var OrderInterface&MailchimpOrderAwareInterface $order */
        $order = $this->createMockForIntersectionOfInterfaces([OrderInterface::class, MailchimpOrderAwareInterface::class]);
        $order->method('getId')->willReturn($orderId);
        $order->method('getTokenValue')->willReturn($tokenValue);
        $order->method('getCustomer')->willReturn($customer);
        $order->method('getBillingAddress')->willReturn(null);
        $order->method('getCurrencyCode')->willReturn('EUR');
        $order->method('getTotal')->willReturn(1000);
        $order->method('getItems')->willReturn(new ArrayCollection([]));

        return $order;
    }

    /** @return ChannelInterface&ChannelMailchimpAwareInterface */
    private function createChannelMock(string $code): ChannelInterface&ChannelMailchimpAwareInterface
    {
        /** @var ChannelInterface&ChannelMailchimpAwareInterface $channel */
        $channel = $this->createMockForIntersectionOfInterfaces([ChannelInterface::class, ChannelMailchimpAwareInterface::class]);
        $channel->method('getCode')->willReturn($code);
        $channel->method('getName')->willReturn('Test Store');
        $channel->method('getHostname')->willReturn('https://example.com');
        $channel->method('getContactEmail')->willReturn('test@example.com');
        $channel->method('getLocales')->willReturn(new ArrayCollection([]));
        $channel->method('getCurrencies')->willReturn(new ArrayCollection([]));

        return $channel;
    }
}
