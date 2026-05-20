<?php

declare(strict_types=1);

namespace Tests\Webgriffe\SyliusMailchimpPlugin\Unit\MessageHandler\Order;

use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Sylius\Component\Channel\Repository\ChannelRepositoryInterface;
use Sylius\Component\Core\Repository\OrderRepositoryInterface;
use Tests\Webgriffe\SyliusMailchimpPlugin\Entity\Channel\Channel;
use Tests\Webgriffe\SyliusMailchimpPlugin\Entity\Customer\Customer;
use Tests\Webgriffe\SyliusMailchimpPlugin\Entity\Order\Order;
use Webgriffe\SyliusMailchimpPlugin\Client\MailchimpClientInterface;
use Webgriffe\SyliusMailchimpPlugin\Mapper\EcommerceCustomerMapper;
use Webgriffe\SyliusMailchimpPlugin\Mapper\OrderMapper;
use Webgriffe\SyliusMailchimpPlugin\Mapper\ProductMapper;
use Webgriffe\SyliusMailchimpPlugin\Mapper\ProductVariantMapper;
use Webgriffe\SyliusMailchimpPlugin\Mapper\StoreMapper;
use Webgriffe\SyliusMailchimpPlugin\Message\Order\OrderCreate;
use Webgriffe\SyliusMailchimpPlugin\Message\Order\OrderRemove;
use Webgriffe\SyliusMailchimpPlugin\Message\Order\OrderUpdate;
use Webgriffe\SyliusMailchimpPlugin\MessageHandler\Order\OrderCreateHandler;
use Webgriffe\SyliusMailchimpPlugin\MessageHandler\Order\OrderRemoveHandler;
use Webgriffe\SyliusMailchimpPlugin\MessageHandler\Order\OrderUpdateHandler;

final class OrderHandlersTest extends TestCase
{
    private OrderRepositoryInterface $orderRepository;

    private ChannelRepositoryInterface $channelRepository;

    private MailchimpClientInterface $mailchimpClient;

    private EntityManagerInterface $entityManager;

    private OrderMapper $orderMapper;

    private StoreMapper $storeMapper;

    private ProductMapper $productMapper;

    protected function setUp(): void
    {
        $this->orderRepository = $this->createMock(OrderRepositoryInterface::class);
        $this->channelRepository = $this->createMock(ChannelRepositoryInterface::class);
        $this->mailchimpClient = $this->createMock(MailchimpClientInterface::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->orderMapper = new OrderMapper(new EcommerceCustomerMapper());
        $this->storeMapper = new StoreMapper();
        $this->productMapper = new ProductMapper(new ProductVariantMapper());
    }

    public function testOrderCreateCallsUpsertOrderAndPersistsId(): void
    {
        $order = $this->createOrder(orderId: 42);
        $channel = $this->createChannel('WEB');
        $this->orderRepository->method('find')->with(42)->willReturn($order);
        $this->channelRepository->method('find')->with(1)->willReturn($channel);
        $this->mailchimpClient->expects($this->once())->method('upsertOrder');
        $this->entityManager->expects($this->once())->method('flush');

        $handler = new OrderCreateHandler(
            $this->orderRepository,
            $this->channelRepository,
            $this->orderMapper,
            $this->storeMapper,
            $this->productMapper,
            $this->mailchimpClient,
            $this->entityManager,
            new NullLogger(),
        );
        $handler(new OrderCreate(42, 1));

        $this->assertSame('order-42', $order->getMailchimpOrderId());
        $this->assertNull($order->getMailchimpOrderError());
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
            $this->productMapper,
            $this->mailchimpClient,
            $this->entityManager,
            new NullLogger(),
        );
        $handler(new OrderCreate(999, 1));
    }

    public function testOrderCreateSkipsWhenChannelNotFound(): void
    {
        $order = $this->createOrder(orderId: 1);
        $this->orderRepository->method('find')->willReturn($order);
        $this->channelRepository->method('find')->willReturn(null);
        $this->mailchimpClient->expects($this->never())->method('upsertOrder');

        $handler = new OrderCreateHandler(
            $this->orderRepository,
            $this->channelRepository,
            $this->orderMapper,
            $this->storeMapper,
            $this->productMapper,
            $this->mailchimpClient,
            $this->entityManager,
            new NullLogger(),
        );
        $handler(new OrderCreate(1, 999));
    }

    public function testOrderUpdateCallsUpsertOrder(): void
    {
        $order = $this->createOrder(orderId: 5);
        $channel = $this->createChannel('WEB');
        $this->orderRepository->method('find')->willReturn($order);
        $this->channelRepository->method('find')->willReturn($channel);
        $this->mailchimpClient->expects($this->once())->method('upsertOrder');

        $handler = new OrderUpdateHandler(
            $this->orderRepository,
            $this->channelRepository,
            $this->orderMapper,
            $this->storeMapper,
            $this->productMapper,
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

    private function createOrder(int $orderId): Order
    {
        $customer = new Customer();
        self::setId($customer, 1);
        $customer->setEmail('x@example.com');

        $order = new Order();
        self::setId($order, $orderId);
        $order->setCustomer($customer);
        $order->setCurrencyCode('EUR');
        self::setTotal($order, 1000);

        return $order;
    }

    private function createChannel(string $code): Channel
    {
        $channel = new Channel();
        $channel->setCode($code);
        $channel->setName('Test Store');
        $channel->setHostname('https://example.com');
        $channel->setContactEmail('test@example.com');

        return $channel;
    }

    private static function setId(object $entity, int $id): void
    {
        $ref = new \ReflectionProperty($entity, 'id');
        $ref->setValue($entity, $id);
    }

    private static function setTotal(Order $order, int $total): void
    {
        $ref = new \ReflectionProperty($order, 'total');
        $ref->setValue($order, $total);
    }
}
