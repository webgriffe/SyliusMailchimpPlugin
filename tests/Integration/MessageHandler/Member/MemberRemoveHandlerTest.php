<?php

declare(strict_types=1);

namespace Tests\Webgriffe\SyliusMailchimpPlugin\Integration\MessageHandler\Member;

use Fidry\AliceDataFixtures\LoaderInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Tests\Webgriffe\SyliusMailchimpPlugin\Stub\Mailchimp\StubMailchimpClient;
use Webgriffe\SyliusMailchimpPlugin\Message\Member\MemberRemove;
use Webgriffe\SyliusMailchimpPlugin\MessageHandler\Member\MemberRemoveHandler;

final class MemberRemoveHandlerTest extends KernelTestCase
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

    public function test_member_remove_calls_remove_member(): void
    {
        $this->fixtureLoader->load([]);

        $handler = self::getContainer()->get(MemberRemoveHandler::class);
        $subscriberHash = md5('test@example.com');
        $handler(new MemberRemove(1, 'test-list-id', $subscriberHash));

        self::assertCount(0, $this->stub->getUpsertMemberCalls());
    }
}
