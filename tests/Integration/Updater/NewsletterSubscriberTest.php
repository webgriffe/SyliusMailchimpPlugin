<?php

declare(strict_types=1);

namespace Tests\Webgriffe\SyliusMailchimpPlugin\Integration\Updater;

use Doctrine\ORM\EntityManagerInterface;
use Fidry\AliceDataFixtures\LoaderInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Tests\Webgriffe\SyliusMailchimpPlugin\Entity\Customer\Customer;
use Tests\Webgriffe\SyliusMailchimpPlugin\Stub\Mailchimp\StubMailchimpClient;
use Webgriffe\SyliusMailchimpPlugin\Client\Exception\ComplianceStateException;
use Webgriffe\SyliusMailchimpPlugin\Updater\NewsletterSubscriberInterface;

final class NewsletterSubscriberTest extends KernelTestCase
{
    private const FIXTURE_BASE_DIR = __DIR__ . '/../../DataFixtures/ORM/resources/Updater/NewsletterSubscriberTest';

    private LoaderInterface $fixtureLoader;

    private StubMailchimpClient $stub;

    protected function setUp(): void
    {
        static::bootKernel();
        $this->fixtureLoader = self::getContainer()->get('fidry_alice_data_fixtures.doctrine.loader');
        $this->stub = self::getContainer()->get(StubMailchimpClient::class);
        $this->stub->reset();
    }

    public function test_it_upserts_member_and_marks_matching_customer_as_subscribed(): void
    {
        $this->fixtureLoader->load([self::FIXTURE_BASE_DIR . '/customer.yaml']);

        $subscriber = self::getContainer()->get(NewsletterSubscriberInterface::class);
        $subscriber->subscribe('newsletter-subscribe@test.com', 'test-list-id');

        self::assertCount(1, $this->stub->getUpsertMemberCalls());

        $em = self::getContainer()->get(EntityManagerInterface::class);
        $customer = $em->getRepository(Customer::class)->findOneBy(['email' => 'newsletter-subscribe@test.com']);
        $em->refresh($customer);
        self::assertTrue($customer->isSubscribedToNewsletter());
        self::assertSame(md5('newsletter-subscribe@test.com'), $customer->getMailchimpId());
        self::assertNotNull($customer->getMailchimpSyncedAt());
        self::assertNull($customer->getMailchimpError());
    }

    public function test_it_does_not_fail_when_no_customer_matches_the_email(): void
    {
        $this->fixtureLoader->load([]);

        $subscriber = self::getContainer()->get(NewsletterSubscriberInterface::class);
        $subscriber->subscribe('unknown@test.com', 'test-list-id');

        self::assertCount(1, $this->stub->getUpsertMemberCalls());
    }

    public function test_it_rethrows_compliance_state_exception_without_updating_customer(): void
    {
        $this->fixtureLoader->load([self::FIXTURE_BASE_DIR . '/customer.yaml']);
        $this->stub->failWith('upsertMember', 'compliance');

        $subscriber = self::getContainer()->get(NewsletterSubscriberInterface::class);

        $this->expectException(ComplianceStateException::class);

        try {
            $subscriber->subscribe('newsletter-subscribe@test.com', 'test-list-id');
        } finally {
            $em = self::getContainer()->get(EntityManagerInterface::class);
            $customer = $em->getRepository(Customer::class)->findOneBy(['email' => 'newsletter-subscribe@test.com']);
            $em->refresh($customer);
            self::assertFalse($customer->isSubscribedToNewsletter());
            self::assertNull($customer->getMailchimpId());
            self::assertNotNull($customer->getMailchimpError());
        }
    }
}
