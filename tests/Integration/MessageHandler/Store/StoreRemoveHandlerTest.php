<?php

declare(strict_types=1);

namespace Tests\Webgriffe\SyliusMailchimpPlugin\Integration\MessageHandler\Store;

use Fidry\AliceDataFixtures\LoaderInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Tests\Webgriffe\SyliusMailchimpPlugin\Stub\Mailchimp\StubMailchimpClient;
use Webgriffe\SyliusMailchimpPlugin\Message\Store\StoreRemove;
use Webgriffe\SyliusMailchimpPlugin\MessageHandler\Store\StoreRemoveHandler;

final class StoreRemoveHandlerTest extends KernelTestCase
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

    public function test_store_remove_calls_remove_store(): void
    {
        $this->fixtureLoader->load([]);

        $handler = self::getContainer()->get(StoreRemoveHandler::class);
        $handler(new StoreRemove('test-store-id'));

        self::assertCount(1, $this->stub->getRemoveStoreCalls());
        self::assertSame('test-store-id', $this->stub->getRemoveStoreCalls()[0]['storeId']);
    }
}
