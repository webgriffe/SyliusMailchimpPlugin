<?php

declare(strict_types=1);

namespace Tests\Webgriffe\SyliusMailchimpPlugin\Integration\MessageHandler\Member;

use Doctrine\ORM\EntityManagerInterface;
use Fidry\AliceDataFixtures\LoaderInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Tests\Webgriffe\SyliusMailchimpPlugin\Entity\Customer\Customer;
use Tests\Webgriffe\SyliusMailchimpPlugin\Stub\Mailchimp\StubMailchimpClient;
use Webgriffe\SyliusMailchimpPlugin\Message\Member\MemberCreate;
use Webgriffe\SyliusMailchimpPlugin\MessageHandler\Member\MemberCreateHandler;

final class MemberCreateHandlerTest extends KernelTestCase
{
    private const FIXTURE_BASE_DIR = __DIR__ . '/../../../DataFixtures/ORM/resources/MessageHandler/Member/MemberCreateHandlerTest';

    private LoaderInterface $fixtureLoader;

    private StubMailchimpClient $stub;

    protected function setUp(): void
    {
        static::bootKernel();
        $this->fixtureLoader = self::getContainer()->get('fidry_alice_data_fixtures.doctrine.loader');
        $this->stub = self::getContainer()->get(StubMailchimpClient::class);
        $this->stub->reset();
    }

    public function test_member_create_upserts_member_and_updates_customer(): void
    {
        $this->fixtureLoader->load([self::FIXTURE_BASE_DIR . '/customer.yaml']);

        $em = self::getContainer()->get(EntityManagerInterface::class);
        $customer = $em->getRepository(Customer::class)->findOneBy(['email' => 'member-create@test.com']);

        $handler = self::getContainer()->get(MemberCreateHandler::class);
        $handler(new MemberCreate($customer->getId(), 'test-list-id'));

        self::assertCount(1, $this->stub->getUpsertMemberCalls());
        $em->refresh($customer);
        self::assertNotNull($customer->getMailchimpId());
        self::assertNotNull($customer->getMailchimpSyncedAt());
    }

    public function test_member_create_skips_when_customer_not_found(): void
    {
        $this->fixtureLoader->load([]);

        $handler = self::getContainer()->get(MemberCreateHandler::class);
        $handler(new MemberCreate(99999, 'test-list-id'));

        self::assertCount(0, $this->stub->getUpsertMemberCalls());
    }
}
