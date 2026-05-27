<?php

declare(strict_types=1);

namespace Tests\Webgriffe\SyliusMailchimpPlugin\Integration\MessageHandler\Store;

use Fidry\AliceDataFixtures\LoaderInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Tests\Webgriffe\SyliusMailchimpPlugin\Entity\Channel\Channel;
use Tests\Webgriffe\SyliusMailchimpPlugin\Stub\Mailchimp\StubMailchimpClient;
use Webgriffe\SyliusMailchimpPlugin\Message\Store\StoreUpdate;
use Webgriffe\SyliusMailchimpPlugin\MessageHandler\Store\StoreUpdateHandler;

final class StoreUpdateHandlerTest extends KernelTestCase
{
    private const FIXTURE_BASE_DIR = __DIR__ . '/../../../DataFixtures/ORM/resources/MessageHandler/Store/StoreUpdateHandlerTest';

    private LoaderInterface $fixtureLoader;

    private StubMailchimpClient $stub;

    protected function setUp(): void
    {
        static::bootKernel();
        $this->fixtureLoader = self::getContainer()->get('fidry_alice_data_fixtures.doctrine.loader');
        $this->stub = self::getContainer()->get(StubMailchimpClient::class);
        $this->stub->reset();
    }

    public function test_store_update_calls_upsert_store(): void
    {
        $this->fixtureLoader->load([self::FIXTURE_BASE_DIR . '/channel.yaml']);

        $channel = self::getContainer()->get('doctrine.orm.entity_manager')
            ->getRepository(Channel::class)
            ->findOneBy(['code' => 'STORE_UPDATE_TEST']);

        $handler = self::getContainer()->get(StoreUpdateHandler::class);
        $handler(new StoreUpdate($channel->getId()));

        self::assertCount(1, $this->stub->getUpsertStoreCalls());
    }
}
