<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260110110250 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE bookings (id UUID NOT NULL, title VARCHAR(255) NOT NULL, started_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, ended_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, participants_count INT NOT NULL, is_private BOOLEAN NOT NULL, status VARCHAR(20) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, room_id UUID DEFAULT NULL, user_id UUID DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_7A853C3554177093 ON bookings (room_id)');
        $this->addSql('CREATE INDEX IDX_7A853C35A76ED395 ON bookings (user_id)');
        $this->addSql('CREATE TABLE booking_participants (booking_id UUID NOT NULL, user_id UUID NOT NULL, PRIMARY KEY (booking_id, user_id))');
        $this->addSql('CREATE INDEX IDX_93F6915D3301C60 ON booking_participants (booking_id)');
        $this->addSql('CREATE INDEX IDX_93F6915DA76ED395 ON booking_participants (user_id)');
        $this->addSql('CREATE TABLE equipment (id UUID NOT NULL, name VARCHAR(100) NOT NULL, category VARCHAR(50) NOT NULL, quantity INT NOT NULL, room_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_D338D58354177093 ON equipment (room_id)');
        $this->addSql('CREATE TABLE issue_history (id UUID NOT NULL, action VARCHAR(50) NOT NULL, description TEXT NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, issue_id UUID NOT NULL, user_id UUID DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_824235AA5E7AA58C ON issue_history (issue_id)');
        $this->addSql('CREATE INDEX IDX_824235AAA76ED395 ON issue_history (user_id)');
        $this->addSql('CREATE TABLE issue_notes (id UUID NOT NULL, content TEXT NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, issue_id UUID NOT NULL, author_id UUID DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_9391CFDF5E7AA58C ON issue_notes (issue_id)');
        $this->addSql('CREATE INDEX IDX_9391CFDFF675F31B ON issue_notes (author_id)');
        $this->addSql('CREATE TABLE organizations (id UUID NOT NULL, regon VARCHAR(255) NOT NULL, name VARCHAR(255) NOT NULL, email VARCHAR(255) NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_427C1C7F4C3FA511 ON organizations (regon)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_427C1C7FE7927C74 ON organizations (email)');
        $this->addSql('CREATE TABLE room_issues (id UUID NOT NULL, category VARCHAR(50) NOT NULL, description TEXT NOT NULL, status VARCHAR(20) NOT NULL, priority VARCHAR(20) NOT NULL, reported_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, closed_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, room_id UUID NOT NULL, reporter_id UUID NOT NULL, organization_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_9B33B94254177093 ON room_issues (room_id)');
        $this->addSql('CREATE INDEX IDX_9B33B942E1CFE6F5 ON room_issues (reporter_id)');
        $this->addSql('CREATE INDEX IDX_9B33B94232C8A3DE ON room_issues (organization_id)');
        $this->addSql('CREATE TABLE room_statuses (id UUID NOT NULL, status VARCHAR(20) NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, room_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_EB16DED554177093 ON room_statuses (room_id)');
        $this->addSql('CREATE TABLE rooms (id UUID NOT NULL, room_name VARCHAR(100) NOT NULL, capacity INT NOT NULL, size DOUBLE PRECISION NOT NULL, location VARCHAR(255) NOT NULL, access VARCHAR(50) NOT NULL, description TEXT DEFAULT NULL, lighting VARCHAR(100) DEFAULT NULL, air_conditioning JSON DEFAULT NULL, image_paths JSON DEFAULT NULL, organization_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_7CA11A9632C8A3DE ON rooms (organization_id)');
        $this->addSql('CREATE TABLE users (id UUID NOT NULL, username VARCHAR(180) NOT NULL, roles JSON NOT NULL, password VARCHAR(255) NOT NULL, first_name VARCHAR(255) NOT NULL, last_name VARCHAR(255) NOT NULL, email VARCHAR(100) NOT NULL, phone VARCHAR(20) NOT NULL, is_active BOOLEAN DEFAULT true NOT NULL, email_notifications_enabled BOOLEAN DEFAULT true NOT NULL, reset_token VARCHAR(64) DEFAULT NULL, reset_token_expires_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, organization_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_1483A5E9F85E0677 ON users (username)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_1483A5E9E7927C74 ON users (email)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_1483A5E9444F97DD ON users (phone)');
        $this->addSql('CREATE INDEX IDX_1483A5E932C8A3DE ON users (organization_id)');
        $this->addSql('CREATE TABLE user_favorite_rooms (user_id UUID NOT NULL, room_id UUID NOT NULL, PRIMARY KEY (user_id, room_id))');
        $this->addSql('CREATE INDEX IDX_A1ACEEEBA76ED395 ON user_favorite_rooms (user_id)');
        $this->addSql('CREATE INDEX IDX_A1ACEEEB54177093 ON user_favorite_rooms (room_id)');
        $this->addSql('ALTER TABLE bookings ADD CONSTRAINT FK_7A853C3554177093 FOREIGN KEY (room_id) REFERENCES rooms (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('ALTER TABLE bookings ADD CONSTRAINT FK_7A853C35A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('ALTER TABLE booking_participants ADD CONSTRAINT FK_93F6915D3301C60 FOREIGN KEY (booking_id) REFERENCES bookings (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE booking_participants ADD CONSTRAINT FK_93F6915DA76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE equipment ADD CONSTRAINT FK_D338D58354177093 FOREIGN KEY (room_id) REFERENCES rooms (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE issue_history ADD CONSTRAINT FK_824235AA5E7AA58C FOREIGN KEY (issue_id) REFERENCES room_issues (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE issue_history ADD CONSTRAINT FK_824235AAA76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('ALTER TABLE issue_notes ADD CONSTRAINT FK_9391CFDF5E7AA58C FOREIGN KEY (issue_id) REFERENCES room_issues (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE issue_notes ADD CONSTRAINT FK_9391CFDFF675F31B FOREIGN KEY (author_id) REFERENCES users (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('ALTER TABLE room_issues ADD CONSTRAINT FK_9B33B94254177093 FOREIGN KEY (room_id) REFERENCES rooms (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE room_issues ADD CONSTRAINT FK_9B33B942E1CFE6F5 FOREIGN KEY (reporter_id) REFERENCES users (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE room_issues ADD CONSTRAINT FK_9B33B94232C8A3DE FOREIGN KEY (organization_id) REFERENCES organizations (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE room_statuses ADD CONSTRAINT FK_EB16DED554177093 FOREIGN KEY (room_id) REFERENCES rooms (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE rooms ADD CONSTRAINT FK_7CA11A9632C8A3DE FOREIGN KEY (organization_id) REFERENCES organizations (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE users ADD CONSTRAINT FK_1483A5E932C8A3DE FOREIGN KEY (organization_id) REFERENCES organizations (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE user_favorite_rooms ADD CONSTRAINT FK_A1ACEEEBA76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE user_favorite_rooms ADD CONSTRAINT FK_A1ACEEEB54177093 FOREIGN KEY (room_id) REFERENCES rooms (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE bookings DROP CONSTRAINT FK_7A853C3554177093');
        $this->addSql('ALTER TABLE bookings DROP CONSTRAINT FK_7A853C35A76ED395');
        $this->addSql('ALTER TABLE booking_participants DROP CONSTRAINT FK_93F6915D3301C60');
        $this->addSql('ALTER TABLE booking_participants DROP CONSTRAINT FK_93F6915DA76ED395');
        $this->addSql('ALTER TABLE equipment DROP CONSTRAINT FK_D338D58354177093');
        $this->addSql('ALTER TABLE issue_history DROP CONSTRAINT FK_824235AA5E7AA58C');
        $this->addSql('ALTER TABLE issue_history DROP CONSTRAINT FK_824235AAA76ED395');
        $this->addSql('ALTER TABLE issue_notes DROP CONSTRAINT FK_9391CFDF5E7AA58C');
        $this->addSql('ALTER TABLE issue_notes DROP CONSTRAINT FK_9391CFDFF675F31B');
        $this->addSql('ALTER TABLE room_issues DROP CONSTRAINT FK_9B33B94254177093');
        $this->addSql('ALTER TABLE room_issues DROP CONSTRAINT FK_9B33B942E1CFE6F5');
        $this->addSql('ALTER TABLE room_issues DROP CONSTRAINT FK_9B33B94232C8A3DE');
        $this->addSql('ALTER TABLE room_statuses DROP CONSTRAINT FK_EB16DED554177093');
        $this->addSql('ALTER TABLE rooms DROP CONSTRAINT FK_7CA11A9632C8A3DE');
        $this->addSql('ALTER TABLE users DROP CONSTRAINT FK_1483A5E932C8A3DE');
        $this->addSql('ALTER TABLE user_favorite_rooms DROP CONSTRAINT FK_A1ACEEEBA76ED395');
        $this->addSql('ALTER TABLE user_favorite_rooms DROP CONSTRAINT FK_A1ACEEEB54177093');
        $this->addSql('DROP TABLE bookings');
        $this->addSql('DROP TABLE booking_participants');
        $this->addSql('DROP TABLE equipment');
        $this->addSql('DROP TABLE issue_history');
        $this->addSql('DROP TABLE issue_notes');
        $this->addSql('DROP TABLE organizations');
        $this->addSql('DROP TABLE room_issues');
        $this->addSql('DROP TABLE room_statuses');
        $this->addSql('DROP TABLE rooms');
        $this->addSql('DROP TABLE users');
        $this->addSql('DROP TABLE user_favorite_rooms');
    }
}
