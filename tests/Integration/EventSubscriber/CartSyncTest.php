<?php

declare(strict_types=1);

namespace Tests\Webgriffe\SyliusMailchimpPlugin\Integration\EventSubscriber;

use Doctrine\ORM\EntityManagerInterface;
use Fidry\AliceDataFixtures\LoaderInterface;
use Sylius\Bundle\OrderBundle\Controller\AddToCartCommand;
use Sylius\Component\Order\SyliusCartEvents;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\GenericEvent;
use Tests\Webgriffe\SyliusMailchimpPlugin\Entity\Order\Order;
use Tests\Webgriffe\SyliusMailchimpPlugin\Stub\Mailchimp\StubMailchimpClient;

final class CartSyncTest extends KernelTestCase
{
    private const FIXTURE_BASE_DIR = __DIR__ . '/../../DataFixtures/ORM/resources/EventSubscriber/CartSyncTest';

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

    public function test_cart_is_created_in_mailchimp_when_item_is_added(): void
    {
        $this->fixtureLoader->load([self::FIXTURE_BASE_DIR . '/cart.yaml']);

        $em = self::getContainer()->get(EntityManagerInterface::class);
        $order = $em->getRepository(Order::class)->findOneBy(['localeCode' => 'en_US']);

        $item = $order->getItems()->first();
        $this->dispatcher->dispatch(
            new GenericEvent(new AddToCartCommand($order, $item)),
            SyliusCartEvents::CART_ITEM_ADD,
        );

        self::assertCount(1, $this->stub->getUpsertCartCalls());
        self::assertSame((string) $order->getId(), $this->stub->getUpsertCartCalls()[0]['cart']->id);
    }

    public function test_cart_is_updated_in_mailchimp_when_item_is_added_again(): void
    {
        $this->fixtureLoader->load([self::FIXTURE_BASE_DIR . '/cart.yaml']);

        $em = self::getContainer()->get(EntityManagerInterface::class);
        $order = $em->getRepository(Order::class)->findOneBy(['localeCode' => 'en_US']);
        $order->setMailchimpCartId((string) $order->getId());
        $em->flush();

        $item = $order->getItems()->first();
        $this->dispatcher->dispatch(
            new GenericEvent(new AddToCartCommand($order, $item)),
            SyliusCartEvents::CART_ITEM_ADD,
        );

        self::assertCount(1, $this->stub->getUpsertCartCalls());
        self::assertSame((string) $order->getId(), $this->stub->getUpsertCartCalls()[0]['cart']->id);
        self::assertCount(0, $this->stub->getRemoveCartCalls());
    }

    public function test_cart_is_updated_in_mailchimp_when_item_is_removed(): void
    {
        $this->fixtureLoader->load([self::FIXTURE_BASE_DIR . '/cart.yaml']);

        $em = self::getContainer()->get(EntityManagerInterface::class);
        $order = $em->getRepository(Order::class)->findOneBy(['localeCode' => 'en_US']);
        $order->setMailchimpCartId((string) $order->getId());
        $em->flush();

        $item = $order->getItems()->first();
        $this->dispatcher->dispatch(
            new GenericEvent($item),
            SyliusCartEvents::CART_ITEM_REMOVE,
        );

        self::assertCount(1, $this->stub->getUpsertCartCalls());
        self::assertSame((string) $order->getId(), $this->stub->getUpsertCartCalls()[0]['cart']->id);
    }

    public function test_cart_is_removed_from_mailchimp_when_cleared(): void
    {
        $this->fixtureLoader->load([self::FIXTURE_BASE_DIR . '/cart.yaml']);

        $em = self::getContainer()->get(EntityManagerInterface::class);
        $order = $em->getRepository(Order::class)->findOneBy(['localeCode' => 'en_US']);
        $order->setMailchimpCartId((string) $order->getId());
        $em->flush();

        $this->dispatcher->dispatch(
            new GenericEvent($order),
            SyliusCartEvents::CART_CLEAR,
        );

        self::assertCount(1, $this->stub->getRemoveCartCalls());
        self::assertCount(0, $this->stub->getUpsertCartCalls());
    }
}
