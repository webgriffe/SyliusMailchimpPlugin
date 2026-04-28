<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260428000001 extends AbstractMigration
{
    #[\Override]
    public function getDescription(): string
    {
        return 'Add Mailchimp columns to sylius_customer, sylius_order, and sylius_channel';
    }

    #[\Override]
    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE sylius_customer ADD mailchimp_id VARCHAR(32) DEFAULT NULL, ADD mailchimp_synced_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', ADD mailchimp_error LONGTEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE sylius_order ADD mailchimp_order_id VARCHAR(255) DEFAULT NULL, ADD mailchimp_cart_id VARCHAR(255) DEFAULT NULL, ADD mailchimp_order_error LONGTEXT DEFAULT NULL, ADD mailchimp_cart_error LONGTEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE sylius_channel ADD mailchimp_audience_id VARCHAR(50) DEFAULT NULL, ADD mailchimp_newsletter_positions JSON DEFAULT NULL');
    }

    #[\Override]
    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE sylius_customer DROP mailchimp_id, DROP mailchimp_synced_at, DROP mailchimp_error');
        $this->addSql('ALTER TABLE sylius_order DROP mailchimp_order_id, DROP mailchimp_cart_id, DROP mailchimp_order_error, DROP mailchimp_cart_error');
        $this->addSql('ALTER TABLE sylius_channel DROP mailchimp_audience_id, DROP mailchimp_newsletter_positions');
    }
}
