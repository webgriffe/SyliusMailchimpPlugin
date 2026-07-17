<?php

declare(strict_types=1);

namespace Tests\Webgriffe\SyliusMailchimpPlugin\Integration\MessageHandler\Member;

use Doctrine\ORM\EntityManagerInterface;
use Fidry\AliceDataFixtures\LoaderInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Tests\Webgriffe\SyliusMailchimpPlugin\Entity\Customer\Customer;
use Tests\Webgriffe\SyliusMailchimpPlugin\Stub\Mailchimp\StubMailchimpClient;
use Webgriffe\SyliusMailchimpPlugin\Client\Exception\ClientException;
use Webgriffe\SyliusMailchimpPlugin\Message\Member\MemberUpdate;
use Webgriffe\SyliusMailchimpPlugin\MessageHandler\Member\MemberUpdateHandler;

final class MemberUpdateHandlerTest extends KernelTestCase
{
    private const FIXTURE_BASE_DIR = __DIR__ . '/../../../DataFixtures/ORM/resources/MessageHandler/Member/MemberUpdateHandlerTest';

    private LoaderInterface $fixtureLoader;

    private StubMailchimpClient $stub;

    protected function setUp(): void
    {
        static::bootKernel();
        $this->fixtureLoader = self::getContainer()->get('fidry_alice_data_fixtures.doctrine.loader');
        $this->stub = self::getContainer()->get(StubMailchimpClient::class);
        $this->stub->reset();
    }

    public function test_member_update_upserts_member_and_updates_customer(): void
    {
        $this->fixtureLoader->load([self::FIXTURE_BASE_DIR . '/customer.yaml']);

        $em = self::getContainer()->get(EntityManagerInterface::class);
        $customer = $em->getRepository(Customer::class)->findOneBy(['email' => 'member-update@test.com']);

        $handler = self::getContainer()->get(MemberUpdateHandler::class);
        $handler(new MemberUpdate($customer->getId(), 'test-list-id'));

        self::assertCount(1, $this->stub->getUpsertMemberCalls());
        $em->refresh($customer);
        self::assertNotNull($customer->getMailchimpId());
    }

    public function test_member_update_throws_on_transient_mailchimp_failure(): void
    {
        $this->fixtureLoader->load([self::FIXTURE_BASE_DIR . '/customer.yaml']);

        $em = self::getContainer()->get(EntityManagerInterface::class);
        $customer = $em->getRepository(Customer::class)->findOneBy(['email' => 'member-update@test.com']);
        $this->stub->failWith('upsertMember');

        $handler = self::getContainer()->get(MemberUpdateHandler::class);

        $this->expectException(ClientException::class);
        $handler(new MemberUpdate($customer->getId(), 'test-list-id'));
    }

    public function test_member_update_persists_error_on_permanent_mailchimp_failure(): void
    {
        $this->fixtureLoader->load([self::FIXTURE_BASE_DIR . '/customer.yaml']);

        $em = self::getContainer()->get(EntityManagerInterface::class);
        $customer = $em->getRepository(Customer::class)->findOneBy(['email' => 'member-update@test.com']);
        $this->stub->failWith('upsertMember', statusCode: 400);

        $handler = self::getContainer()->get(MemberUpdateHandler::class);
        $handler(new MemberUpdate($customer->getId(), 'test-list-id'));

        $em->refresh($customer);
        self::assertNotNull($customer->getMailchimpError());
    }
}
