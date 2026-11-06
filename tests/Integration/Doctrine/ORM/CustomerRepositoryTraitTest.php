<?php

declare(strict_types=1);

namespace Tests\Webgriffe\SyliusMailchimpPlugin\Integration\Doctrine\ORM;

use Fidry\AliceDataFixtures\LoaderInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Webgriffe\SyliusMailchimpPlugin\Repository\MailchimpCustomerRepositoryInterface;

final class CustomerRepositoryTraitTest extends KernelTestCase
{
    private const FIXTURE_FILE = __DIR__ . '/../../../DataFixtures/ORM/resources/Doctrine/ORM/CustomerRepositoryTraitTest/customers.yaml';

    private MailchimpCustomerRepositoryInterface $repository;

    /** @var array<string, object> */
    private array $fixtures;

    protected function setUp(): void
    {
        self::bootKernel();
        $loader = self::getContainer()->get('fidry_alice_data_fixtures.doctrine.loader');
        assert($loader instanceof LoaderInterface);
        $this->fixtures = $loader->load([self::FIXTURE_FILE]);
        $repository = self::getContainer()->get('sylius.repository.customer');
        assert($repository instanceof MailchimpCustomerRepositoryInterface);
        $this->repository = $repository;
    }

    public function test_customer_sync_queries_skip_failed_customers_by_default(): void
    {
        $since = new \DateTimeImmutable('-1 day');

        self::assertSame($this->idsOf('subscribed_never_synced'), $this->idsFromResult($this->repository->findMailchimpSyncNeeded()));
        self::assertSame($this->idsOf('subscribed_never_synced'), $this->idsFromResult($this->repository->findMailchimpSyncNeededSince($since)));
    }

    public function test_customer_sync_queries_include_failed_customers_on_request(): void
    {
        $since = new \DateTimeImmutable('-1 day');
        $expected = $this->idsOf('subscribed_never_synced', 'subscribed_failed');

        self::assertSame($expected, $this->idsFromResult($this->repository->findMailchimpSyncNeeded(true)));
        self::assertSame($expected, $this->idsFromResult($this->repository->findMailchimpSyncNeededSince($since, true)));
    }

    public function test_customer_sync_needed_since_returns_only_subscribed_customers(): void
    {
        $result = $this->repository->findMailchimpSyncNeededSince(new \DateTimeImmutable('-1 day'), true);

        self::assertNotContains($this->idsOf('unsubscribed_never_synced')[0], $this->idsFromResult($result));
    }

    public function test_find_by_ids_ignores_the_error_flag(): void
    {
        $ids = $this->idsOf('subscribed_failed');

        self::assertSame($ids, $this->idsFromResult($this->repository->findMailchimpByIds($ids)));
    }

    /** @return list<int> */
    private function idsOf(string ...$keys): array
    {
        $ids = array_map(fn (string $key): int => $this->fixtures[$key]->getId(), $keys);
        sort($ids);

        return $ids;
    }

    /**
     * @param object[] $result
     *
     * @return list<int>
     */
    private function idsFromResult(array $result): array
    {
        $ids = array_map(static fn (object $customer): int => $customer->getId(), $result);
        sort($ids);

        return $ids;
    }
}
