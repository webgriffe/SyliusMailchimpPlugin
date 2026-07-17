<?php

declare(strict_types=1);

namespace Tests\Webgriffe\SyliusMailchimpPlugin\Integration\MessageHandler\Cart;

use Doctrine\ORM\EntityManagerInterface;
use Fidry\AliceDataFixtures\LoaderInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Tests\Webgriffe\SyliusMailchimpPlugin\Entity\Order\Order;
use Tests\Webgriffe\SyliusMailchimpPlugin\Stub\Mailchimp\StubMailchimpClient;
use Webgriffe\SyliusMailchimpPlugin\Client\Exception\ClientException;
use Webgriffe\SyliusMailchimpPlugin\Message\Cart\CartCreate;
use Webgriffe\SyliusMailchimpPlugin\MessageHandler\Cart\CartCreateHandler;

final class CartCreateHandlerTest extends KernelTestCase
{
    private const FIXTURE_BASE_DIR = __DIR__ . '/../../../DataFixtures/ORM/resources/MessageHandler/Cart/CartCreateHandlerTest';

    private LoaderInterface $fixtureLoader;

    private StubMailchimpClient $stub;

    protected function setUp(): void
    {
        static::bootKernel();
        $this->fixtureLoader = self::getContainer()->get('fidry_alice_data_fixtures.doctrine.loader');
        $this->stub = self::getContainer()->get(StubMailchimpClient::class);
        $this->stub->reset();
    }

    public function test_cart_create_calls_upsert_cart_and_persists_mailchimp_cart_id(): void
    {
        $this->fixtureLoader->load([self::FIXTURE_BASE_DIR . '/order.yaml']);

        $em = self::getContainer()->get(EntityManagerInterface::class);
        $order = $em->getRepository(Order::class)->findOneBy(['localeCode' => 'en_US']);

        $handler = self::getContainer()->get(CartCreateHandler::class);
        $handler(new CartCreate($order->getId()));

        self::assertCount(1, $this->stub->getUpsertCartCalls());
        $em->refresh($order);
        self::assertSame((string) $order->getId(), $order->getMailchimpCartId());
    }

    public function test_cart_create_throws_on_transient_mailchimp_failure(): void
    {
        $this->fixtureLoader->load([self::FIXTURE_BASE_DIR . '/order.yaml']);

        $em = self::getContainer()->get(EntityManagerInterface::class);
        $order = $em->getRepository(Order::class)->findOneBy(['localeCode' => 'en_US']);
        $this->stub->failWith('upsertCart');

        $handler = self::getContainer()->get(CartCreateHandler::class);

        $this->expectException(ClientException::class);
        $handler(new CartCreate($order->getId()));
    }

    public function test_cart_create_persists_cart_error_on_permanent_mailchimp_failure(): void
    {
        $this->fixtureLoader->load([self::FIXTURE_BASE_DIR . '/order.yaml']);

        $em = self::getContainer()->get(EntityManagerInterface::class);
        $order = $em->getRepository(Order::class)->findOneBy(['localeCode' => 'en_US']);
        $this->stub->failWith('upsertCart', statusCode: 400);

        $handler = self::getContainer()->get(CartCreateHandler::class);
        $handler(new CartCreate($order->getId()));

        $em->refresh($order);
        self::assertNotNull($order->getMailchimpCartError());
        self::assertStringContainsString('Simulated Mailchimp failure', $order->getMailchimpCartError());
    }

    public function test_cart_create_skips_when_order_not_found(): void
    {
        $this->fixtureLoader->load([]);

        $handler = self::getContainer()->get(CartCreateHandler::class);
        $handler(new CartCreate(99999));

        self::assertCount(0, $this->stub->getUpsertCartCalls());
    }
}
