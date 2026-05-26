<?php

declare(strict_types=1);

namespace Tests\Webgriffe\SyliusMailchimpPlugin\Unit\MessageHandler\Order;

use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Sylius\Component\Core\Repository\OrderRepositoryInterface;
use Tests\Webgriffe\SyliusMailchimpPlugin\Entity\Channel\Channel;
use Tests\Webgriffe\SyliusMailchimpPlugin\Entity\Customer\Customer;
use Tests\Webgriffe\SyliusMailchimpPlugin\Entity\Order\Order;
use Webgriffe\SyliusMailchimpPlugin\Client\MailchimpClientInterface;
use Webgriffe\SyliusMailchimpPlugin\Mapper\EcommerceCustomerMapper;
use Webgriffe\SyliusMailchimpPlugin\Mapper\OrderMapper;
use Webgriffe\SyliusMailchimpPlugin\Mapper\ProductMapperInterface;
use Webgriffe\SyliusMailchimpPlugin\Message\Order\OrderCreate;
use Webgriffe\SyliusMailchimpPlugin\Message\Order\OrderRemove;
use Webgriffe\SyliusMailchimpPlugin\Message\Order\OrderUpdate;
use Webgriffe\SyliusMailchimpPlugin\MessageHandler\Order\OrderCreateHandler;
use Webgriffe\SyliusMailchimpPlugin\MessageHandler\Order\OrderRemoveHandler;
use Webgriffe\SyliusMailchimpPlugin\MessageHandler\Order\OrderUpdateHandler;
use Webgriffe\SyliusMailchimpPlugin\Provider\AudienceProviderInterface;
use Webgriffe\SyliusMailchimpPlugin\Resolver\StoreIdentifierResolverInterface;
use Webgriffe\SyliusMailchimpPlugin\ValueObject\Audience;

final class OrderHandlersTest extends TestCase
{
    private MockObject&OrderRepositoryInterface $orderRepository;

    private MockObject&MailchimpClientInterface $mailchimpClient;

    private MockObject&EntityManagerInterface $entityManager;

    private OrderMapper $orderMapper;

    private MockObject&AudienceProviderInterface $audienceProvider;

    private MockObject&StoreIdentifierResolverInterface $storeIdentifierResolver;

    private MockObject&ProductMapperInterface $productMapper;

    protected function setUp(): void
    {
        $this->orderRepository = $this->createMock(OrderRepositoryInterface::class);
        $this->mailchimpClient = $this->createMock(MailchimpClientInterface::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->orderMapper = new OrderMapper(new EcommerceCustomerMapper());
        $this->audienceProvider = $this->createMock(AudienceProviderInterface::class);
        $this->storeIdentifierResolver = $this->createMock(StoreIdentifierResolverInterface::class);
        $this->storeIdentifierResolver->method('resolve')->willReturn('WEB-abc123');
        $this->productMapper = $this->createMock(ProductMapperInterface::class);
    }

    public function testOrderCreateCallsUpsertOrderAndPersistsId(): void
    {
        $channel = $this->createChannel('WEB');
        $order = $this->createOrder(orderId: 42, channel: $channel);
        $this->orderRepository->method('find')->with(42)->willReturn($order);
        $this->audienceProvider->method('getAudience')->willReturn(new Audience('abc123', $channel));
        $this->mailchimpClient->expects($this->once())->method('upsertOrder');
        $this->entityManager->expects($this->once())->method('flush');

        $handler = $this->makeCreateHandler();
        $handler(new OrderCreate(42));

        $this->assertSame('42', $order->getMailchimpOrderId());
        $this->assertNull($order->getMailchimpOrderError());
    }

    public function testOrderCreateSkipsWhenOrderNotFound(): void
    {
        $this->orderRepository->method('find')->willReturn(null);
        $this->mailchimpClient->expects($this->never())->method('upsertOrder');

        $handler = $this->makeCreateHandler();
        $handler(new OrderCreate(999));
    }

    public function testOrderCreateSkipsWhenOrderHasNoMailchimpChannel(): void
    {
        $order = $this->createOrder(orderId: 1, channel: null);
        $this->orderRepository->method('find')->willReturn($order);
        $this->mailchimpClient->expects($this->never())->method('upsertOrder');

        $handler = $this->makeCreateHandler();
        $handler(new OrderCreate(1));
    }

    public function testOrderUpdateCallsUpsertOrder(): void
    {
        $channel = $this->createChannel('WEB');
        $order = $this->createOrder(orderId: 5, channel: $channel);
        $this->orderRepository->method('find')->willReturn($order);
        $this->audienceProvider->method('getAudience')->willReturn(new Audience('abc123', $channel));
        $this->mailchimpClient->expects($this->once())->method('upsertOrder');

        $handler = $this->makeUpdateHandler();
        $handler(new OrderUpdate(5));
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

    private function makeCreateHandler(): OrderCreateHandler
    {
        return new OrderCreateHandler(
            $this->orderRepository,
            $this->orderMapper,
            $this->audienceProvider,
            $this->storeIdentifierResolver,
            $this->productMapper,
            $this->mailchimpClient,
            $this->entityManager,
            new NullLogger(),
        );
    }

    private function makeUpdateHandler(): OrderUpdateHandler
    {
        return new OrderUpdateHandler(
            $this->orderRepository,
            $this->orderMapper,
            $this->audienceProvider,
            $this->storeIdentifierResolver,
            $this->productMapper,
            $this->mailchimpClient,
            $this->entityManager,
            new NullLogger(),
        );
    }

    private function createOrder(int $orderId, ?Channel $channel): Order
    {
        $customer = new Customer();
        self::setId($customer, 1);
        $customer->setEmail('x@example.com');

        $order = new Order();
        self::setId($order, $orderId);
        $order->setCustomer($customer);
        $order->setCurrencyCode('EUR');
        if ($channel !== null) {
            $order->setChannel($channel);
        }
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
        $channel->setMailchimpAudienceId('abc123');

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
