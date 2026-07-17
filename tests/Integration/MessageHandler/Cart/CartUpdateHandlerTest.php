<?php

declare(strict_types=1);

namespace Tests\Webgriffe\SyliusMailchimpPlugin\Integration\MessageHandler\Cart;

use Doctrine\ORM\EntityManagerInterface;
use Fidry\AliceDataFixtures\LoaderInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Tests\Webgriffe\SyliusMailchimpPlugin\Entity\Order\Order;
use Tests\Webgriffe\SyliusMailchimpPlugin\Stub\Mailchimp\StubMailchimpClient;
use Webgriffe\SyliusMailchimpPlugin\Client\Exception\ClientException;
use Webgriffe\SyliusMailchimpPlugin\Message\Cart\CartUpdate;
use Webgriffe\SyliusMailchimpPlugin\MessageHandler\Cart\CartUpdateHandler;

final class CartUpdateHandlerTest extends KernelTestCase
{
    private const FIXTURE_BASE_DIR = __DIR__ . '/../../../DataFixtures/ORM/resources/MessageHandler/Cart/CartUpdateHandlerTest';

    private LoaderInterface $fixtureLoader;

    private StubMailchimpClient $stub;

    protected function setUp(): void
    {
        static::bootKernel();
        $this->fixtureLoader = self::getContainer()->get('fidry_alice_data_fixtures.doctrine.loader');
        $this->stub = self::getContainer()->get(StubMailchimpClient::class);
        $this->stub->reset();
    }

    public function test_cart_update_calls_upsert_cart(): void
    {
        $this->fixtureLoader->load([self::FIXTURE_BASE_DIR . '/order.yaml']);

        $em = self::getContainer()->get(EntityManagerInterface::class);
        $order = $em->getRepository(Order::class)->findOneBy(['localeCode' => 'en_US']);
        $order->setMailchimpCartId((string) $order->getId());
        $em->flush();

        $handler = self::getContainer()->get(CartUpdateHandler::class);
        $handler(new CartUpdate($order->getId()));

        self::assertCount(1, $this->stub->getUpsertCartCalls());
    }

    public function test_cart_update_throws_on_transient_mailchimp_failure(): void
    {
        $this->fixtureLoader->load([self::FIXTURE_BASE_DIR . '/order.yaml']);

        $em = self::getContainer()->get(EntityManagerInterface::class);
        $order = $em->getRepository(Order::class)->findOneBy(['localeCode' => 'en_US']);
        $order->setMailchimpCartId((string) $order->getId());
        $em->flush();
        $this->stub->failWith('upsertCart');

        $handler = self::getContainer()->get(CartUpdateHandler::class);

        $this->expectException(ClientException::class);
        $handler(new CartUpdate($order->getId()));
    }

    public function test_cart_update_persists_cart_error_on_permanent_mailchimp_failure(): void
    {
        $this->fixtureLoader->load([self::FIXTURE_BASE_DIR . '/order.yaml']);

        $em = self::getContainer()->get(EntityManagerInterface::class);
        $order = $em->getRepository(Order::class)->findOneBy(['localeCode' => 'en_US']);
        $order->setMailchimpCartId((string) $order->getId());
        $em->flush();
        $this->stub->failWith('upsertCart', statusCode: 400);

        $handler = self::getContainer()->get(CartUpdateHandler::class);
        $handler(new CartUpdate($order->getId()));

        $em->refresh($order);
        self::assertNotNull($order->getMailchimpCartError());
    }

    public function test_cart_update_resets_cart_error_on_success(): void
    {
        $this->fixtureLoader->load([self::FIXTURE_BASE_DIR . '/order.yaml']);

        $em = self::getContainer()->get(EntityManagerInterface::class);
        $order = $em->getRepository(Order::class)->findOneBy(['localeCode' => 'en_US']);
        $order->setMailchimpCartId((string) $order->getId());
        $order->setMailchimpCartError('previous error');
        $em->flush();

        $handler = self::getContainer()->get(CartUpdateHandler::class);
        $handler(new CartUpdate($order->getId()));

        $em->refresh($order);
        self::assertNull($order->getMailchimpCartError());
    }
}
