<?php

declare(strict_types=1);

namespace Tests\Webgriffe\SyliusMailchimpPlugin\Unit\MessageHandler\Cart;

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
use Webgriffe\SyliusMailchimpPlugin\Message\Cart\CartUpdate;
use Webgriffe\SyliusMailchimpPlugin\MessageHandler\Cart\CartUpdateHandler;
use Webgriffe\SyliusMailchimpPlugin\Provider\AudienceProviderInterface;
use Webgriffe\SyliusMailchimpPlugin\Resolver\StoreIdentifierResolverInterface;
use Webgriffe\SyliusMailchimpPlugin\Util\IdSanitizer;
use Webgriffe\SyliusMailchimpPlugin\ValueObject\Audience;

final class CartUpdateHandlerTest extends TestCase
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
        $this->mailchimpClient->expects(self::never())->method('upsertCart');

        $handler = $this->buildHandler();
        $handler(new CartUpdate(1));
    }

    public function test_skips_when_channel_is_not_mailchimp_aware(): void
    {
        $order = new Order();
        $order->setChannel(new \Sylius\Component\Core\Model\Channel());
        $this->orderRepository->method('find')->with(1)->willReturn($order);
        $this->mailchimpClient->expects(self::never())->method('upsertCart');

        $handler = $this->buildHandler();
        $handler(new CartUpdate(1));
    }

    public function test_upserts_cart_and_sets_mailchimp_cart_id(): void
    {
        $channel = new Channel();
        $order = new Order();
        self::setIdOnObject($order, 99);
        $order->setChannel($channel);
        $order->setLocaleCode('en_US');

        $audience = new Audience('list123', $channel);
        $this->orderRepository->method('find')->with(99)->willReturn($order);
        $this->audienceProvider->method('getAudience')->with($channel, 'en_US')->willReturn($audience);
        $this->storeIdentifierResolver->method('resolve')->with($audience)->willReturn('web-store');
        $this->mailchimpClient->expects(self::once())->method('upsertCart')->with('web-store', $order, $channel);
        $this->entityManager->expects(self::once())->method('flush');

        $handler = $this->buildHandler();
        $handler(new CartUpdate(99));

        self::assertSame(IdSanitizer::sanitize('99'), $order->getMailchimpCartId());
        self::assertNull($order->getMailchimpCartError());
    }

    public function test_upserts_products_before_cart_for_each_unique_product(): void
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
        $this->mailchimpClient->expects(self::once())->method('upsertCart');

        $handler = $this->buildHandler();
        $handler(new CartUpdate(10));
    }

    private function buildHandler(): CartUpdateHandler
    {
        return new CartUpdateHandler(
            $this->orderRepository,
            $this->audienceProvider,
            $this->storeIdentifierResolver,
            $this->mailchimpClient,
            $this->entityManager,
            new NullLogger(),
        );
    }
}
