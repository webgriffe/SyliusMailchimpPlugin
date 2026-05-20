<?php

declare(strict_types=1);

namespace Tests\Webgriffe\SyliusMailchimpPlugin\Unit\MessageHandler\Cart;

use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Sylius\Component\Channel\Repository\ChannelRepositoryInterface;
use Sylius\Component\Core\Repository\OrderRepositoryInterface;
use Tests\Webgriffe\SyliusMailchimpPlugin\Entity\Channel\Channel;
use Tests\Webgriffe\SyliusMailchimpPlugin\Entity\Customer\Customer;
use Tests\Webgriffe\SyliusMailchimpPlugin\Entity\Order\Order;
use Webgriffe\SyliusMailchimpPlugin\Client\MailchimpClientInterface;
use Webgriffe\SyliusMailchimpPlugin\Mapper\CartMapper;
use Webgriffe\SyliusMailchimpPlugin\Mapper\EcommerceCustomerMapper;
use Webgriffe\SyliusMailchimpPlugin\Mapper\ProductMapper;
use Webgriffe\SyliusMailchimpPlugin\Mapper\ProductVariantMapper;
use Webgriffe\SyliusMailchimpPlugin\Mapper\StoreMapper;
use Webgriffe\SyliusMailchimpPlugin\Message\Cart\CartCreate;
use Webgriffe\SyliusMailchimpPlugin\Message\Cart\CartRemove;
use Webgriffe\SyliusMailchimpPlugin\Message\Cart\CartUpdate;
use Webgriffe\SyliusMailchimpPlugin\MessageHandler\Cart\CartCreateHandler;
use Webgriffe\SyliusMailchimpPlugin\MessageHandler\Cart\CartRemoveHandler;
use Webgriffe\SyliusMailchimpPlugin\MessageHandler\Cart\CartUpdateHandler;

final class CartHandlersTest extends TestCase
{
    private OrderRepositoryInterface $orderRepository;

    private ChannelRepositoryInterface $channelRepository;

    private MailchimpClientInterface $mailchimpClient;

    private EntityManagerInterface $entityManager;

    private CartMapper $cartMapper;

    private StoreMapper $storeMapper;

    private ProductMapper $productMapper;

    protected function setUp(): void
    {
        $this->orderRepository = $this->createMock(OrderRepositoryInterface::class);
        $this->channelRepository = $this->createMock(ChannelRepositoryInterface::class);
        $this->mailchimpClient = $this->createMock(MailchimpClientInterface::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->cartMapper = new CartMapper(new EcommerceCustomerMapper());
        $this->storeMapper = new StoreMapper();
        $this->productMapper = new ProductMapper(new ProductVariantMapper());
    }

    public function testCartCreateCallsUpsertCartAndPersistsId(): void
    {
        $order = $this->createOrder(orderId: 5);
        $channel = $this->createChannel('WEB');
        $this->orderRepository->method('find')->with(5)->willReturn($order);
        $this->channelRepository->method('find')->with(1)->willReturn($channel);
        $this->mailchimpClient->expects($this->once())->method('upsertCart');
        $this->entityManager->expects($this->once())->method('flush');

        $handler = new CartCreateHandler(
            $this->orderRepository,
            $this->channelRepository,
            $this->cartMapper,
            $this->storeMapper,
            $this->productMapper,
            $this->mailchimpClient,
            $this->entityManager,
            new NullLogger(),
        );
        $handler(new CartCreate(5, 1));

        $this->assertSame('5', $order->getMailchimpCartId());
        $this->assertNull($order->getMailchimpCartError());
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
            $this->productMapper,
            $this->mailchimpClient,
            $this->entityManager,
            new NullLogger(),
        );
        $handler(new CartCreate(999, 1));
    }

    public function testCartUpdateCallsUpsertCart(): void
    {
        $order = $this->createOrder(orderId: 5);
        $channel = $this->createChannel('WEB');
        $this->orderRepository->method('find')->willReturn($order);
        $this->channelRepository->method('find')->willReturn($channel);
        $this->mailchimpClient->expects($this->once())->method('upsertCart');

        $handler = new CartUpdateHandler(
            $this->orderRepository,
            $this->channelRepository,
            $this->cartMapper,
            $this->storeMapper,
            $this->productMapper,
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
