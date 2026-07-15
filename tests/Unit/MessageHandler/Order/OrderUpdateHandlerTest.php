<?php

declare(strict_types=1);

namespace Tests\Webgriffe\SyliusMailchimpPlugin\Unit\MessageHandler\Order;

use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Sylius\Component\Core\Model\OrderItem;
use Sylius\Component\Core\Model\Product;
use Sylius\Component\Core\Model\ProductVariant;
use Sylius\Component\Core\Repository\OrderRepositoryInterface;
use Tests\Webgriffe\SyliusMailchimpPlugin\Entity\Channel\Channel;
use Tests\Webgriffe\SyliusMailchimpPlugin\Entity\Order\Order;
use Tests\Webgriffe\SyliusMailchimpPlugin\Unit\ReflectionIdTrait;
use Webgriffe\SyliusMailchimpPlugin\Client\MailchimpClientInterface;
use Webgriffe\SyliusMailchimpPlugin\Message\Order\OrderUpdate;
use Webgriffe\SyliusMailchimpPlugin\MessageHandler\Order\OrderUpdateHandler;
use Webgriffe\SyliusMailchimpPlugin\Provider\AudienceProviderInterface;
use Webgriffe\SyliusMailchimpPlugin\Resolver\StoreIdentifierResolverInterface;
use Webgriffe\SyliusMailchimpPlugin\Util\IdSanitizer;
use Webgriffe\SyliusMailchimpPlugin\ValueObject\Audience;

final class OrderUpdateHandlerTest extends TestCase
{
    use ReflectionIdTrait;

    private MockObject&OrderRepositoryInterface $orderRepository;

    private MockObject&AudienceProviderInterface $audienceProvider;

    private MockObject&StoreIdentifierResolverInterface $storeIdentifierResolver;

    private MockObject&MailchimpClientInterface $mailchimpClient;

    private MockObject&EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        $this->orderRepository = $this->createMock(OrderRepositoryInterface::class);
        $this->audienceProvider = $this->createMock(AudienceProviderInterface::class);
        $this->storeIdentifierResolver = $this->createMock(StoreIdentifierResolverInterface::class);
        $this->mailchimpClient = $this->createMock(MailchimpClientInterface::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
    }

    public function test_skips_when_order_not_found(): void
    {
        $this->orderRepository->method('find')->with(1)->willReturn(null);
        $this->mailchimpClient->expects(self::never())->method('upsertOrder');

        $handler = $this->buildHandler();
        $handler(new OrderUpdate(1));
    }

    public function test_skips_when_channel_is_not_mailchimp_aware(): void
    {
        $order = new Order();
        $order->setChannel(new \Sylius\Component\Core\Model\Channel());
        $this->orderRepository->method('find')->with(1)->willReturn($order);
        $this->mailchimpClient->expects(self::never())->method('upsertOrder');

        $handler = $this->buildHandler();
        $handler(new OrderUpdate(1));
    }

    public function test_upserts_order_and_sets_mailchimp_order_id(): void
    {
        $channel = new Channel();
        $order = new Order();
        self::setIdOnObject($order, 55);
        $order->setChannel($channel);
        $order->setLocaleCode('en_US');

        $audience = new Audience('list123', $channel);
        $this->orderRepository->method('find')->with(55)->willReturn($order);
        $this->audienceProvider->method('getAudience')->with($channel, 'en_US')->willReturn($audience);
        $this->storeIdentifierResolver->method('resolve')->with($audience)->willReturn('web-store');
        $this->mailchimpClient->expects(self::once())->method('upsertOrder')->with('web-store', $order);
        $this->entityManager->expects(self::once())->method('flush');

        $handler = $this->buildHandler();
        $handler(new OrderUpdate(55));

        self::assertSame(IdSanitizer::sanitize('55'), $order->getMailchimpOrderId());
        self::assertNull($order->getMailchimpOrderError());
    }

    public function test_upserts_products_before_order_for_each_unique_product(): void
    {
        $channel = new Channel();

        $productA = new Product();
        $productA->setCode('PROD-A');
        self::setIdOnObject($productA, 1);

        $productB = new Product();
        $productB->setCode('PROD-B');
        self::setIdOnObject($productB, 2);

        $variantA = new ProductVariant();
        $productA->addVariant($variantA);

        $variantB = new ProductVariant();
        $productB->addVariant($variantB);

        $itemA = new OrderItem();
        $itemA->setVariant($variantA);

        $itemB = new OrderItem();
        $itemB->setVariant($variantB);

        $itemADuplicate = new OrderItem();
        $itemADuplicate->setVariant($variantA);

        $order = new Order();
        self::setIdOnObject($order, 10);
        $order->setChannel($channel);
        $order->setLocaleCode('en_US');
        $order->addItem($itemA);
        $order->addItem($itemB);
        $order->addItem($itemADuplicate);

        $audience = new Audience('list123', $channel);
        $this->orderRepository->method('find')->with(10)->willReturn($order);
        $this->audienceProvider->method('getAudience')->willReturn($audience);
        $this->storeIdentifierResolver->method('resolve')->willReturn('web-store');

        $this->mailchimpClient->expects(self::exactly(2))->method('upsertProduct');
        $this->mailchimpClient->expects(self::once())->method('upsertOrder');

        $handler = $this->buildHandler();
        $handler(new OrderUpdate(10));
    }

    private function buildHandler(): OrderUpdateHandler
    {
        return new OrderUpdateHandler(
            $this->orderRepository,
            $this->audienceProvider,
            $this->storeIdentifierResolver,
            $this->mailchimpClient,
            $this->entityManager,
            new NullLogger(),
        );
    }
}
