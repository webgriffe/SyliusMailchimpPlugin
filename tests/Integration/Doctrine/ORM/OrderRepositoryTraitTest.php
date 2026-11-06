<?php

declare(strict_types=1);

namespace Tests\Webgriffe\SyliusMailchimpPlugin\Integration\Doctrine\ORM;

use Fidry\AliceDataFixtures\LoaderInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Webgriffe\SyliusMailchimpPlugin\Repository\MailchimpOrderRepositoryInterface;

final class OrderRepositoryTraitTest extends KernelTestCase
{
    private const FIXTURE_FILE = __DIR__ . '/../../../DataFixtures/ORM/resources/Doctrine/ORM/OrderRepositoryTraitTest/orders.yaml';

    private MailchimpOrderRepositoryInterface $repository;

    /** @var array<string, object> */
    private array $fixtures;

    protected function setUp(): void
    {
        self::bootKernel();
        $loader = self::getContainer()->get('fidry_alice_data_fixtures.doctrine.loader');
        assert($loader instanceof LoaderInterface);
        $this->fixtures = $loader->load([self::FIXTURE_FILE]);
        $repository = self::getContainer()->get('sylius.repository.order');
        assert($repository instanceof MailchimpOrderRepositoryInterface);
        $this->repository = $repository;
    }

    public function test_cart_sync_needed_updated_since_never_returns_completed_orders(): void
    {
        $result = $this->repository->findMailchimpCartSyncNeededUpdatedSince(new \DateTimeImmutable('-1 day'));

        self::assertSame($this->idsOf('cart_never_synced'), $this->idsFromResult($result));
    }

    public function test_cart_sync_needed_never_returns_completed_orders(): void
    {
        self::assertSame($this->idsOf('cart_never_synced'), $this->idsFromResult($this->repository->findMailchimpCartSyncNeeded()));
    }

    public function test_order_sync_needed_updated_since_never_returns_carts(): void
    {
        $result = $this->repository->findMailchimpOrderSyncNeededUpdatedSince(new \DateTimeImmutable('-1 day'));

        self::assertSame($this->idsOf('completed_never_synced', 'completed_synced_recent'), $this->idsFromResult($result));
    }

    public function test_order_sync_needed_never_returns_carts(): void
    {
        self::assertSame($this->idsOf('completed_never_synced'), $this->idsFromResult($this->repository->findMailchimpOrderSyncNeeded()));
        self::assertSame(
            $this->idsOf('completed_never_synced'),
            $this->idsFromResult($this->repository->findMailchimpOrderSyncNeeded(new \DateTimeImmutable('-1 day'))),
        );
    }

    public function test_order_syncable_returns_only_completed_orders(): void
    {
        self::assertSame(
            $this->idsOf('completed_never_synced', 'completed_synced_recent', 'completed_synced_old'),
            $this->idsFromResult($this->repository->findMailchimpOrderSyncable()),
        );
    }

    public function test_pending_counts_ignore_records_in_the_other_state(): void
    {
        self::assertSame(2, $this->repository->countMailchimpPendingCarts());
        self::assertSame(2, $this->repository->countMailchimpPendingOrders());
    }

    public function test_order_sync_queries_skip_failed_orders_by_default(): void
    {
        $since = new \DateTimeImmutable('-1 day');

        self::assertNotContains($this->idOf('completed_failed'), $this->idsFromResult($this->repository->findMailchimpOrderSyncNeeded()));
        self::assertNotContains($this->idOf('completed_failed'), $this->idsFromResult($this->repository->findMailchimpOrderSyncNeeded($since)));
        self::assertNotContains($this->idOf('completed_failed'), $this->idsFromResult($this->repository->findMailchimpOrderSyncNeededUpdatedSince($since)));
        self::assertNotContains($this->idOf('completed_failed'), $this->idsFromResult($this->repository->findMailchimpOrderSyncable()));
    }

    public function test_order_sync_queries_include_failed_orders_on_request(): void
    {
        $since = new \DateTimeImmutable('-1 day');

        self::assertContains($this->idOf('completed_failed'), $this->idsFromResult($this->repository->findMailchimpOrderSyncNeeded(includeFailed: true)));
        self::assertContains($this->idOf('completed_failed'), $this->idsFromResult($this->repository->findMailchimpOrderSyncNeeded($since, true)));
        self::assertContains($this->idOf('completed_failed'), $this->idsFromResult($this->repository->findMailchimpOrderSyncNeededUpdatedSince($since, true)));
        self::assertContains($this->idOf('completed_failed'), $this->idsFromResult($this->repository->findMailchimpOrderSyncable(true)));
    }

    public function test_cart_sync_queries_skip_failed_carts_by_default(): void
    {
        self::assertNotContains($this->idOf('cart_failed'), $this->idsFromResult($this->repository->findMailchimpCartSyncNeeded()));
        self::assertNotContains(
            $this->idOf('cart_failed'),
            $this->idsFromResult($this->repository->findMailchimpCartSyncNeededUpdatedSince(new \DateTimeImmutable('-1 day'))),
        );
    }

    public function test_cart_sync_queries_include_failed_carts_on_request(): void
    {
        self::assertContains($this->idOf('cart_failed'), $this->idsFromResult($this->repository->findMailchimpCartSyncNeeded(true)));
        self::assertContains(
            $this->idOf('cart_failed'),
            $this->idsFromResult($this->repository->findMailchimpCartSyncNeededUpdatedSince(new \DateTimeImmutable('-1 day'), true)),
        );
    }

    private function idOf(string $key): int
    {
        return $this->fixtures[$key]->getId();
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
        $ids = array_map(static fn (object $order): int => $order->getId(), $result);
        sort($ids);

        return $ids;
    }
}
