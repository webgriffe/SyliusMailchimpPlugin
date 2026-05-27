<?php

declare(strict_types=1);

namespace Tests\Webgriffe\SyliusMailchimpPlugin\Integration\MessageHandler\Order;

use Fidry\AliceDataFixtures\LoaderInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Tests\Webgriffe\SyliusMailchimpPlugin\Stub\Mailchimp\StubMailchimpClient;
use Webgriffe\SyliusMailchimpPlugin\Message\Order\OrderRemove;
use Webgriffe\SyliusMailchimpPlugin\MessageHandler\Order\OrderRemoveHandler;

final class OrderRemoveHandlerTest extends KernelTestCase
{
    private LoaderInterface $fixtureLoader;

    private StubMailchimpClient $stub;

    protected function setUp(): void
    {
        static::bootKernel();
        $this->fixtureLoader = self::getContainer()->get('fidry_alice_data_fixtures.doctrine.loader');
        $this->stub = self::getContainer()->get(StubMailchimpClient::class);
        $this->stub->reset();
    }

    public function test_order_remove_calls_remove_order(): void
    {
        $this->fixtureLoader->load([]);

        $handler = self::getContainer()->get(OrderRemoveHandler::class);
        $handler(new OrderRemove('store-id', 'order-id'));

        self::assertCount(1, $this->stub->getRemoveOrderCalls());
        self::assertSame('store-id', $this->stub->getRemoveOrderCalls()[0]['storeId']);
        self::assertSame('order-id', $this->stub->getRemoveOrderCalls()[0]['orderId']);
    }
}
