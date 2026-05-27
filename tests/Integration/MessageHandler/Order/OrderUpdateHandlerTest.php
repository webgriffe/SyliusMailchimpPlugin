<?php

declare(strict_types=1);

namespace Tests\Webgriffe\SyliusMailchimpPlugin\Integration\MessageHandler\Order;

use Doctrine\ORM\EntityManagerInterface;
use Fidry\AliceDataFixtures\LoaderInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Tests\Webgriffe\SyliusMailchimpPlugin\Entity\Order\Order;
use Tests\Webgriffe\SyliusMailchimpPlugin\Stub\Mailchimp\StubMailchimpClient;
use Webgriffe\SyliusMailchimpPlugin\Message\Order\OrderUpdate;
use Webgriffe\SyliusMailchimpPlugin\MessageHandler\Order\OrderUpdateHandler;

final class OrderUpdateHandlerTest extends KernelTestCase
{
    private const FIXTURE_BASE_DIR = __DIR__ . '/../../../DataFixtures/ORM/resources/MessageHandler/Order/OrderUpdateHandlerTest';

    private LoaderInterface $fixtureLoader;

    private StubMailchimpClient $stub;

    protected function setUp(): void
    {
        static::bootKernel();
        $this->fixtureLoader = self::getContainer()->get('fidry_alice_data_fixtures.doctrine.loader');
        $this->stub = self::getContainer()->get(StubMailchimpClient::class);
        $this->stub->reset();
    }

    public function test_order_update_calls_upsert_order(): void
    {
        $this->fixtureLoader->load([self::FIXTURE_BASE_DIR . '/order.yaml']);

        $em = self::getContainer()->get(EntityManagerInterface::class);
        $order = $em->getRepository(Order::class)->findOneBy(['localeCode' => 'en_US']);
        $order->setMailchimpOrderId((string) $order->getId());
        $em->flush();

        $handler = self::getContainer()->get(OrderUpdateHandler::class);
        $handler(new OrderUpdate($order->getId()));

        self::assertCount(1, $this->stub->getUpsertOrderCalls());
    }
}
