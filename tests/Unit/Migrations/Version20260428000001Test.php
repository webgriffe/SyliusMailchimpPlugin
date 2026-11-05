<?php

declare(strict_types=1);

namespace Tests\Webgriffe\SyliusMailchimpPlugin\Unit\Migrations;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Comparator;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Webgriffe\SyliusMailchimpPlugin\Migrations\Version20260428000001;

final class Version20260428000001Test extends TestCase
{
    private const COLUMNS = [
        'sylius_customer' => ['mailchimp_id', 'mailchimp_synced_at', 'mailchimp_error'],
        'sylius_order' => ['mailchimp_order_id', 'mailchimp_cart_id', 'mailchimp_order_error', 'mailchimp_cart_error'],
        'sylius_channel' => ['mailchimp_audience_id'],
    ];

    public function test_it_adds_all_mailchimp_columns_when_missing(): void
    {
        $schema = $this->createSchema();

        $this->createMigration()->up($schema);

        foreach (self::COLUMNS as $table => $columns) {
            foreach ($columns as $column) {
                self::assertTrue($schema->getTable($table)->hasColumn($column), $table . '.' . $column);
                self::assertFalse($schema->getTable($table)->getColumn($column)->getNotnull(), $table . '.' . $column);
            }
        }
        self::assertSame(32, $schema->getTable('sylius_customer')->getColumn('mailchimp_id')->getLength());
        self::assertSame(50, $schema->getTable('sylius_channel')->getColumn('mailchimp_audience_id')->getLength());
        self::assertSame(255, $schema->getTable('sylius_order')->getColumn('mailchimp_order_id')->getLength());
        self::assertSame(255, $schema->getTable('sylius_order')->getColumn('mailchimp_cart_id')->getLength());
    }

    public function test_it_leaves_existing_columns_untouched(): void
    {
        $schema = $this->createSchema();
        $schema->getTable('sylius_customer')->addColumn('mailchimp_id', Types::STRING, ['length' => 32, 'notnull' => false]);
        $schema->getTable('sylius_order')->addColumn('mailchimp_order_id', Types::STRING, ['length' => 255, 'notnull' => false]);

        $this->createMigration()->up($schema);

        self::assertTrue($schema->getTable('sylius_customer')->hasColumn('mailchimp_error'));
        self::assertTrue($schema->getTable('sylius_order')->hasColumn('mailchimp_cart_id'));
        self::assertTrue($schema->getTable('sylius_channel')->hasColumn('mailchimp_audience_id'));
    }

    public function test_it_adds_nothing_when_all_columns_exist(): void
    {
        $schema = $this->createSchema();
        $this->createMigration()->up($schema);
        $before = clone $schema;

        $this->createMigration()->up($schema);

        self::assertTrue((new Comparator())->compareSchemas($before, $schema)->isEmpty());
    }

    public function test_down_drops_only_existing_columns(): void
    {
        $schema = $this->createSchema();
        $schema->getTable('sylius_customer')->addColumn('mailchimp_id', Types::STRING, ['length' => 32, 'notnull' => false]);

        $this->createMigration()->down($schema);

        foreach (self::COLUMNS as $table => $columns) {
            foreach ($columns as $column) {
                self::assertFalse($schema->getTable($table)->hasColumn($column), $table . '.' . $column);
            }
        }
    }

    private function createSchema(): Schema
    {
        $schema = new Schema();
        foreach (array_keys(self::COLUMNS) as $table) {
            $schema->createTable($table)->addColumn('id', Types::INTEGER);
        }

        return $schema;
    }

    private function createMigration(): Version20260428000001
    {
        return new Version20260428000001($this->createMock(Connection::class), new NullLogger());
    }
}
