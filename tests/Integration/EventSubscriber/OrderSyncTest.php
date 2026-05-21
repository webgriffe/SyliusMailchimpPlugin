<?php

declare(strict_types=1);

namespace Tests\Webgriffe\SyliusMailchimpPlugin\Integration\EventSubscriber;

use Doctrine\ORM\EntityManagerInterface;
use Sylius\Component\Core\Model\ChannelPricing;
use Sylius\Component\Core\Model\OrderItem;
use Sylius\Component\Core\Model\Product;
use Sylius\Component\Core\Model\ProductTranslation;
use Sylius\Component\Core\Model\ProductVariant;
use Sylius\Component\Currency\Model\Currency;
use Sylius\Component\Locale\Model\Locale;
use Sylius\Component\Order\Factory\OrderItemUnitFactoryInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\GenericEvent;
use Tests\Webgriffe\SyliusMailchimpPlugin\Behat\Client\StubMailchimpClient;
use Tests\Webgriffe\SyliusMailchimpPlugin\Entity\Channel\Channel;
use Tests\Webgriffe\SyliusMailchimpPlugin\Entity\Customer\Customer;
use Tests\Webgriffe\SyliusMailchimpPlugin\Entity\Order\Order;

final class OrderSyncTest extends KernelTestCase
{
    private EntityManagerInterface $em;

    private EventDispatcherInterface $dispatcher;

    private OrderItemUnitFactoryInterface $orderItemUnitFactory;

    private ?Channel $createdChannel = null;

    private ?Product $createdProduct = null;

    private ?Order $createdOrder = null;

    private ?Customer $createdCustomer = null;

    private StubMailchimpClient $stub;

    protected function setUp(): void
    {
        static::bootKernel();
        $container = self::getContainer();
        $this->em = $container->get(EntityManagerInterface::class);
        $this->dispatcher = $container->get(EventDispatcherInterface::class);
        $this->orderItemUnitFactory = $container->get('sylius.factory.order_item_unit');
        $this->stub = $container->get(StubMailchimpClient::class);
        $this->stub->reset();
        $this->createdChannel = null;
        $this->createdProduct = null;
        $this->createdOrder = null;
        $this->createdCustomer = null;
    }

    protected function tearDown(): void
    {
        if ($this->createdOrder !== null) {
            $order = $this->em->find(Order::class, $this->createdOrder->getId());
            if ($order !== null) {
                $this->em->remove($order);
            }
        }

        if ($this->createdProduct !== null) {
            $product = $this->em->find(Product::class, $this->createdProduct->getId());
            if ($product !== null) {
                $this->em->remove($product);
            }
        }

        if ($this->createdChannel !== null) {
            $channel = $this->em->find(Channel::class, $this->createdChannel->getId());
            if ($channel !== null) {
                $this->em->remove($channel);
            }
        }

        if ($this->createdCustomer !== null) {
            $customer = $this->em->find(Customer::class, $this->createdCustomer->getId());
            if ($customer !== null) {
                $this->em->remove($customer);
            }
        }

        $this->em->flush();

        parent::tearDown();
    }

    public function testOrderIsCreatedInMailchimpWhenCheckoutIsCompleted(): void
    {
        [$channel, $variant, $order] = $this->createPersistedOrderData();
        $cartId = (string) $order->getId();
        $order->setMailchimpCartId($cartId);
        $this->em->flush();

        $this->dispatcher->dispatch(new GenericEvent($order), 'sylius.order.post_complete');

        self::assertCount(1, $this->stub->getUpsertOrderCalls());
        $mappedOrder = $this->stub->getLastUpsertOrderCall();
        self::assertNotNull($mappedOrder);
        self::assertSame((string) $order->getId(), $mappedOrder->id);
        self::assertSame($cartId, $mappedOrder->cartId);

        $this->em->refresh($order);
        self::assertNotNull($order->getMailchimpOrderId());
    }

    public function testOrderIsUpdatedInMailchimpWhenItWasAlreadySynced(): void
    {
        [$channel, $variant, $order] = $this->createPersistedOrderData();
        $cartId = (string) $order->getId();
        $order->setMailchimpCartId($cartId);
        $order->setMailchimpOrderId((string) $order->getId());
        $this->em->flush();

        $this->dispatcher->dispatch(new GenericEvent($order), 'sylius.order.post_complete');

        self::assertCount(1, $this->stub->getUpsertOrderCalls());
        $mappedOrder = $this->stub->getLastUpsertOrderCall();
        self::assertNotNull($mappedOrder);
        self::assertSame((string) $order->getId(), $mappedOrder->id);
    }

    /**
     * @return array{Channel, ProductVariant, Order}
     */
    private function createPersistedOrderData(): array
    {
        $uid = uniqid('', true);

        /** @var \Sylius\Component\Locale\Model\Locale|null $locale */
        $locale = $this->em->getRepository(Locale::class)->findOneBy(['code' => 'en_US']);
        if ($locale === null) {
            $locale = new Locale();
            $locale->setCode('en_US');
            $this->em->persist($locale);
        }

        /** @var \Sylius\Component\Currency\Model\Currency|null $currency */
        $currency = $this->em->getRepository(Currency::class)->findOneBy(['code' => 'USD']);
        if ($currency === null) {
            $currency = new Currency();
            $currency->setCode('USD');
            $this->em->persist($currency);
        }

        $channel = new Channel();
        $channel->setCode('WEBSTORE_TEST_' . $uid);
        $channel->setName('Web Store');
        $channel->setHostname('localhost');
        $channel->setMailchimpAudienceId('test-audience-id');
        $channel->setTaxCalculationStrategy('order_items_based');
        $channel->addLocale($locale);
        $channel->setDefaultLocale($locale);
        $channel->addCurrency($currency);
        $channel->setBaseCurrency($currency);
        $this->em->persist($channel);

        $productTranslation = new ProductTranslation();
        $productTranslation->setLocale('en_US');
        $productTranslation->setName('Test T-Shirt');
        $productTranslation->setSlug('test-t-shirt-' . $uid);
        $productTranslation->setDescription('A test product.');

        $variant = new ProductVariant();
        $variant->setCode('TEST_T_SHIRT_S_' . $uid);
        $variant->setCurrentLocale('en_US');
        $variant->setFallbackLocale('en_US');
        $variant->setName('S');

        $channelPricing = new ChannelPricing();
        $channelPricing->setChannelCode('WEBSTORE_TEST_' . $uid);
        $channelPricing->setPrice(2999);
        $variant->addChannelPricing($channelPricing);

        $product = new Product();
        $product->setCode('TEST_T_SHIRT_' . $uid);
        $product->addTranslation($productTranslation);
        $product->addVariant($variant);
        $this->em->persist($product);

        $this->em->flush();

        $orderItem = new OrderItem();
        $orderItem->setVariant($variant);
        $orderItem->setUnitPrice(2999);
        $this->orderItemUnitFactory->createForItem($orderItem);

        $order = new Order();
        $order->setChannel($channel);
        $order->setCurrencyCode('USD');
        $order->setLocaleCode('en_US');
        $order->addItem($orderItem);

        $customer = new Customer();
        $customer->setEmail('test-' . $uid . '@example.com');
        $this->em->persist($customer);
        $order->setCustomer($customer);

        $this->em->persist($order);

        $this->em->flush();

        $this->createdChannel = $channel;
        $this->createdProduct = $product;
        $this->createdOrder = $order;
        $this->createdCustomer = $customer;

        return [$channel, $variant, $order];
    }
}
