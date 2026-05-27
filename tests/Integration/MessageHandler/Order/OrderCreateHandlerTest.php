<?php

declare(strict_types=1);

namespace Tests\Webgriffe\SyliusMailchimpPlugin\Integration\MessageHandler\Order;

use Doctrine\ORM\EntityManagerInterface;
use Fidry\AliceDataFixtures\LoaderInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Tests\Webgriffe\SyliusMailchimpPlugin\Entity\Order\Order;
use Tests\Webgriffe\SyliusMailchimpPlugin\Stub\Mailchimp\StubMailchimpClient;
use Webgriffe\SyliusMailchimpPlugin\Message\Order\OrderCreate;
use Webgriffe\SyliusMailchimpPlugin\MessageHandler\Order\OrderCreateHandler;

final class OrderCreateHandlerTest extends KernelTestCase
{
    private const FIXTURE_BASE_DIR = __DIR__ . '/../../../DataFixtures/ORM/resources/MessageHandler/Order/OrderCreateHandlerTest';

    private LoaderInterface $fixtureLoader;

    private StubMailchimpClient $stub;

    protected function setUp(): void
    {
        static::bootKernel();
        $this->fixtureLoader = self::getContainer()->get('fidry_alice_data_fixtures.doctrine.loader');
        $this->stub = self::getContainer()->get(StubMailchimpClient::class);
        $this->stub->reset();
    }

    public function test_order_create_calls_upsert_order_and_persists_mailchimp_order_id(): void
    {
        $this->fixtureLoader->load([self::FIXTURE_BASE_DIR . '/order.yaml']);

        $em = self::getContainer()->get(EntityManagerInterface::class);
        $order = $em->getRepository(Order::class)->findOneBy(['localeCode' => 'en_US']);

        $handler = self::getContainer()->get(OrderCreateHandler::class);
        $handler(new OrderCreate($order->getId()));

        self::assertCount(1, $this->stub->getUpsertOrderCalls());
        $em->refresh($order);
        self::assertSame((string) $order->getId(), $order->getMailchimpOrderId());
    }

    public function test_order_create_skips_when_order_not_found(): void
    {
        $this->fixtureLoader->load([]);

        $handler = self::getContainer()->get(OrderCreateHandler::class);
        $handler(new OrderCreate(99999));

        self::assertCount(0, $this->stub->getUpsertOrderCalls());
    }
}
