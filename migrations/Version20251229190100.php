<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Add email_notifications_enabled column to users table
 */
final class Version20251229190100 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add email_notifications_enabled column to users table with default value true';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users ADD email_notifications_enabled BOOLEAN DEFAULT true NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users DROP email_notifications_enabled');
    }
}
