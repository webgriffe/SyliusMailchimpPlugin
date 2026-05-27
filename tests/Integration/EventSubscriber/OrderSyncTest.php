<?php

declare(strict_types=1);

namespace Tests\Webgriffe\SyliusMailchimpPlugin\Integration\EventSubscriber;

use Doctrine\ORM\EntityManagerInterface;
use Fidry\AliceDataFixtures\LoaderInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\GenericEvent;
use Tests\Webgriffe\SyliusMailchimpPlugin\Entity\Order\Order;
use Tests\Webgriffe\SyliusMailchimpPlugin\Stub\Mailchimp\StubMailchimpClient;

final class OrderSyncTest extends KernelTestCase
{
    private const FIXTURE_BASE_DIR = __DIR__ . '/../../DataFixtures/ORM/resources/EventSubscriber/OrderSyncTest';

    private LoaderInterface $fixtureLoader;

    private EventDispatcherInterface $dispatcher;

    private StubMailchimpClient $stub;

    protected function setUp(): void
    {
        static::bootKernel();
        $this->fixtureLoader = self::getContainer()->get('fidry_alice_data_fixtures.doctrine.loader');
        $this->dispatcher = self::getContainer()->get(EventDispatcherInterface::class);
        $this->stub = self::getContainer()->get(StubMailchimpClient::class);
        $this->stub->reset();
    }

    public function test_order_is_created_in_mailchimp_when_checkout_is_completed(): void
    {
        $this->fixtureLoader->load([self::FIXTURE_BASE_DIR . '/order.yaml']);

        $em = self::getContainer()->get(EntityManagerInterface::class);
        $order = $em->getRepository(Order::class)->findOneBy(['localeCode' => 'en_US']);
        $cartId = (string) $order->getId();
        $order->setMailchimpCartId($cartId);
        $em->flush();

        $this->dispatcher->dispatch(new GenericEvent($order), 'sylius.order.post_complete');

        self::assertCount(1, $this->stub->getUpsertOrderCalls());
        $mappedOrder = $this->stub->getLastUpsertOrderCall();
        self::assertNotNull($mappedOrder);
        self::assertSame((string) $order->getId(), $mappedOrder->id);
        self::assertSame($cartId, $mappedOrder->cartId);

        $em->refresh($order);
        self::assertNotNull($order->getMailchimpOrderId());
    }

    public function test_order_is_updated_in_mailchimp_when_it_was_already_synced(): void
    {
        $this->fixtureLoader->load([self::FIXTURE_BASE_DIR . '/order.yaml']);

        $em = self::getContainer()->get(EntityManagerInterface::class);
        $order = $em->getRepository(Order::class)->findOneBy(['localeCode' => 'en_US']);
        $cartId = (string) $order->getId();
        $order->setMailchimpCartId($cartId);
        $order->setMailchimpOrderId((string) $order->getId());
        $em->flush();

        $this->dispatcher->dispatch(new GenericEvent($order), 'sylius.order.post_complete');

        self::assertCount(1, $this->stub->getUpsertOrderCalls());
        $mappedOrder = $this->stub->getLastUpsertOrderCall();
        self::assertNotNull($mappedOrder);
        self::assertSame((string) $order->getId(), $mappedOrder->id);
    }
}
