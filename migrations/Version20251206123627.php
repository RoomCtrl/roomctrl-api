<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251206123627 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE organizations (id UUID NOT NULL, regon VARCHAR(255) NOT NULL, name VARCHAR(255) NOT NULL, email VARCHAR(255) NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_427C1C7F4C3FA511 ON organizations (regon)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_427C1C7FE7927C74 ON organizations (email)');
        $this->addSql('CREATE TABLE users (id UUID NOT NULL, username VARCHAR(180) NOT NULL, roles JSON NOT NULL, password VARCHAR(255) NOT NULL, first_name VARCHAR(255) NOT NULL, last_name VARCHAR(255) NOT NULL, first_login_status BOOLEAN NOT NULL, email VARCHAR(100) NOT NULL, phone VARCHAR(20) NOT NULL, reset_token VARCHAR(64) DEFAULT NULL, reset_token_expires_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, organization_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_1483A5E9F85E0677 ON users (username)');
        $this->addSql('CREATE INDEX IDX_1483A5E932C8A3DE ON users (organization_id)');
        $this->addSql('ALTER TABLE users ADD CONSTRAINT FK_1483A5E932C8A3DE FOREIGN KEY (organization_id) REFERENCES organizations (id) NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE users DROP CONSTRAINT FK_1483A5E932C8A3DE');
        $this->addSql('DROP TABLE organizations');
        $this->addSql('DROP TABLE users');
    }
}
