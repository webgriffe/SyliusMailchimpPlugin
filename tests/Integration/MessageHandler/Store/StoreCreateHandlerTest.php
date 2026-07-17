<?php

declare(strict_types=1);

namespace Tests\Webgriffe\SyliusMailchimpPlugin\Integration\MessageHandler\Store;

use Fidry\AliceDataFixtures\LoaderInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Tests\Webgriffe\SyliusMailchimpPlugin\Entity\Channel\Channel;
use Tests\Webgriffe\SyliusMailchimpPlugin\Stub\Mailchimp\StubMailchimpClient;
use Webgriffe\SyliusMailchimpPlugin\Client\Exception\ClientException;
use Webgriffe\SyliusMailchimpPlugin\Message\Store\StoreCreate;
use Webgriffe\SyliusMailchimpPlugin\MessageHandler\Store\StoreCreateHandler;

final class StoreCreateHandlerTest extends KernelTestCase
{
    private const FIXTURE_BASE_DIR = __DIR__ . '/../../../DataFixtures/ORM/resources/MessageHandler/Store/StoreCreateHandlerTest';

    private LoaderInterface $fixtureLoader;

    private StubMailchimpClient $stub;

    protected function setUp(): void
    {
        static::bootKernel();
        $this->fixtureLoader = self::getContainer()->get('fidry_alice_data_fixtures.doctrine.loader');
        $this->stub = self::getContainer()->get(StubMailchimpClient::class);
        $this->stub->reset();
    }

    public function test_store_create_calls_upsert_store(): void
    {
        $this->fixtureLoader->load([self::FIXTURE_BASE_DIR . '/channel.yaml']);

        $channel = self::getContainer()->get('doctrine.orm.entity_manager')
            ->getRepository(Channel::class)
            ->findOneBy(['code' => 'STORE_CREATE_TEST']);

        $handler = self::getContainer()->get(StoreCreateHandler::class);
        $handler(new StoreCreate($channel->getId()));

        self::assertCount(1, $this->stub->getUpsertStoreCalls());
    }

    public function test_store_create_throws_on_transient_mailchimp_failure(): void
    {
        $this->fixtureLoader->load([self::FIXTURE_BASE_DIR . '/channel.yaml']);

        $channel = self::getContainer()->get('doctrine.orm.entity_manager')
            ->getRepository(Channel::class)
            ->findOneBy(['code' => 'STORE_CREATE_TEST']);
        $this->stub->failWith('upsertStore');

        $handler = self::getContainer()->get(StoreCreateHandler::class);

        $this->expectException(ClientException::class);
        $handler(new StoreCreate($channel->getId()));
    }

    public function test_store_create_does_not_throw_on_permanent_mailchimp_failure(): void
    {
        $this->fixtureLoader->load([self::FIXTURE_BASE_DIR . '/channel.yaml']);

        $channel = self::getContainer()->get('doctrine.orm.entity_manager')
            ->getRepository(Channel::class)
            ->findOneBy(['code' => 'STORE_CREATE_TEST']);
        $this->stub->failWith('upsertStore', statusCode: 400);

        $handler = self::getContainer()->get(StoreCreateHandler::class);
        $handler(new StoreCreate($channel->getId()));

        self::assertCount(0, $this->stub->getUpsertStoreCalls());
    }

    public function test_store_create_skips_when_channel_not_found(): void
    {
        $this->fixtureLoader->load([]);

        $handler = self::getContainer()->get(StoreCreateHandler::class);
        $handler(new StoreCreate(99999));

        self::assertCount(0, $this->stub->getUpsertStoreCalls());
    }
}
