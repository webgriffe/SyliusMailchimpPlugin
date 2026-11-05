<?php

declare(strict_types=1);

namespace Webgriffe\SyliusMailchimpPlugin\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;
use Doctrine\Migrations\AbstractMigration;

final class Version20260428000001 extends AbstractMigration
{
    private const COLUMNS = [
        'sylius_customer' => [
            'mailchimp_id' => [Types::STRING, ['length' => 32]],
            'mailchimp_synced_at' => [Types::DATETIME_IMMUTABLE, []],
            'mailchimp_error' => [Types::TEXT, []],
        ],
        'sylius_order' => [
            'mailchimp_order_id' => [Types::STRING, ['length' => 255]],
            'mailchimp_cart_id' => [Types::STRING, ['length' => 255]],
            'mailchimp_order_error' => [Types::TEXT, []],
            'mailchimp_cart_error' => [Types::TEXT, []],
        ],
        'sylius_channel' => [
            'mailchimp_audience_id' => [Types::STRING, ['length' => 50]],
        ],
    ];

    #[\Override]
    public function getDescription(): string
    {
        return 'Add Mailchimp columns to sylius_customer, sylius_order, and sylius_channel';
    }

    #[\Override]
    public function up(Schema $schema): void
    {
        foreach (self::COLUMNS as $tableName => $columns) {
            $table = $schema->getTable($tableName);
            foreach ($columns as $columnName => [$type, $options]) {
                if (!$table->hasColumn($columnName)) {
                    $table->addColumn($columnName, $type, $options + ['notnull' => false]);
                }
            }
        }
    }

    #[\Override]
    public function down(Schema $schema): void
    {
        foreach (self::COLUMNS as $tableName => $columns) {
            $table = $schema->getTable($tableName);
            foreach (array_keys($columns) as $columnName) {
                if ($table->hasColumn($columnName)) {
                    $table->dropColumn($columnName);
                }
            }
        }
    }
}
