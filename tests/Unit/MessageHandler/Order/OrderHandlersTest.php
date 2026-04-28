<?php

declare(strict_types=1);

namespace Tests\Webgriffe\SyliusMailchimpPlugin\Unit\MessageHandler\Order;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Sylius\Component\Channel\Repository\ChannelRepositoryInterface;
use Sylius\Component\Core\Model\AdjustmentInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\CustomerInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Repository\OrderRepositoryInterface;
use Webgriffe\SyliusMailchimpPlugin\Client\MailchimpClientInterface;
use Webgriffe\SyliusMailchimpPlugin\Mapper\EcommerceCustomerMapper;
use Webgriffe\SyliusMailchimpPlugin\Mapper\OrderMapper;
use Webgriffe\SyliusMailchimpPlugin\Mapper\StoreMapper;
use Webgriffe\SyliusMailchimpPlugin\Message\Order\OrderCreate;
use Webgriffe\SyliusMailchimpPlugin\Message\Order\OrderRemove;
use Webgriffe\SyliusMailchimpPlugin\Message\Order\OrderUpdate;
use Webgriffe\SyliusMailchimpPlugin\MessageHandler\Order\OrderCreateHandler;
use Webgriffe\SyliusMailchimpPlugin\MessageHandler\Order\OrderRemoveHandler;
use Webgriffe\SyliusMailchimpPlugin\MessageHandler\Order\OrderUpdateHandler;
use Webgriffe\SyliusMailchimpPlugin\Model\ChannelMailchimpAwareInterface;
use Webgriffe\SyliusMailchimpPlugin\Model\MailchimpOrderAwareInterface;

final class OrderHandlersTest extends TestCase
{
    private OrderRepositoryInterface $orderRepository;

    private ChannelRepositoryInterface $channelRepository;

    private MailchimpClientInterface $mailchimpClient;

    private EntityManagerInterface $entityManager;

    private OrderMapper $orderMapper;

    private StoreMapper $storeMapper;

    protected function setUp(): void
    {
        $this->orderRepository = $this->createMock(OrderRepositoryInterface::class);
        $this->channelRepository = $this->createMock(ChannelRepositoryInterface::class);
        $this->mailchimpClient = $this->createMock(MailchimpClientInterface::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->orderMapper = new OrderMapper(new EcommerceCustomerMapper());
        $this->storeMapper = new StoreMapper();
    }

    public function testOrderCreateCallsUpsertOrderAndPersistsId(): void
    {
        $order = $this->createOrderMock(orderId: 42);
        $channel = $this->createChannelMock('WEB');
        $this->orderRepository->method('find')->with(42)->willReturn($order);
        $this->channelRepository->method('find')->with(1)->willReturn($channel);
        $this->mailchimpClient->expects($this->once())->method('upsertOrder');
        $order->expects($this->once())->method('setMailchimpOrderId')->with('order-42');
        $order->expects($this->once())->method('setMailchimpOrderError')->with(null);
        $this->entityManager->expects($this->once())->method('flush');

        $handler = new OrderCreateHandler(
            $this->orderRepository,
            $this->channelRepository,
            $this->orderMapper,
            $this->storeMapper,
            $this->mailchimpClient,
            $this->entityManager,
            new NullLogger(),
        );
        $handler(new OrderCreate(42, 1));
    }

    public function testOrderCreateSkipsWhenOrderNotFound(): void
    {
        $this->orderRepository->method('find')->willReturn(null);
        $this->mailchimpClient->expects($this->never())->method('upsertOrder');

        $handler = new OrderCreateHandler(
            $this->orderRepository,
            $this->channelRepository,
            $this->orderMapper,
            $this->storeMapper,
            $this->mailchimpClient,
            $this->entityManager,
            new NullLogger(),
        );
        $handler(new OrderCreate(999, 1));
    }

    public function testOrderCreateSkipsWhenChannelNotFound(): void
    {
        $order = $this->createOrderMock(orderId: 1);
        $this->orderRepository->method('find')->willReturn($order);
        $this->channelRepository->method('find')->willReturn(null);
        $this->mailchimpClient->expects($this->never())->method('upsertOrder');

        $handler = new OrderCreateHandler(
            $this->orderRepository,
            $this->channelRepository,
            $this->orderMapper,
            $this->storeMapper,
            $this->mailchimpClient,
            $this->entityManager,
            new NullLogger(),
        );
        $handler(new OrderCreate(1, 999));
    }

    public function testOrderUpdateCallsUpsertOrder(): void
    {
        $order = $this->createOrderMock(orderId: 5);
        $channel = $this->createChannelMock('WEB');
        $this->orderRepository->method('find')->willReturn($order);
        $this->channelRepository->method('find')->willReturn($channel);
        $this->mailchimpClient->expects($this->once())->method('upsertOrder');

        $handler = new OrderUpdateHandler(
            $this->orderRepository,
            $this->channelRepository,
            $this->orderMapper,
            $this->storeMapper,
            $this->mailchimpClient,
            $this->entityManager,
            new NullLogger(),
        );
        $handler(new OrderUpdate(5, 1));
    }

    public function testOrderRemoveCallsRemoveOrder(): void
    {
        $this->mailchimpClient->expects($this->once())->method('removeOrder')->with('WEB', 'order-42');

        $handler = new OrderRemoveHandler($this->mailchimpClient, new NullLogger());
        $handler(new OrderRemove('WEB', 'order-42'));
    }

    public function testOrderRemoveRethrowsException(): void
    {
        $this->mailchimpClient->method('removeOrder')->willThrowException(new \RuntimeException('API error'));

        $handler = new OrderRemoveHandler($this->mailchimpClient, new NullLogger());

        $this->expectException(\RuntimeException::class);
        $handler(new OrderRemove('WEB', 'order-42'));
    }

    /** @return OrderInterface&MailchimpOrderAwareInterface */
    private function createOrderMock(int $orderId): OrderInterface&MailchimpOrderAwareInterface
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
        $order->method('getCustomer')->willReturn($customer);
        $order->method('getBillingAddress')->willReturn(null);
        $order->method('getShippingAddress')->willReturn(null);
        $order->method('getCurrencyCode')->willReturn('EUR');
        $order->method('getTotal')->willReturn(1000);
        $order->method('getTaxTotal')->willReturn(100);
        $order->method('getShippingTotal')->willReturn(200);
        $order->method('getAdjustmentsTotalRecursively')
            ->with(AdjustmentInterface::ORDER_PROMOTION_ADJUSTMENT)
            ->willReturn(0);
        $order->method('getItems')->willReturn(new ArrayCollection([]));
        $order->method('getCheckoutCompletedAt')->willReturn(null);

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
