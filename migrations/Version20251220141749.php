<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251220141749 extends AbstractMigration
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
        $this->addSql('CREATE TABLE organizations (id UUID NOT NULL, regon VARCHAR(255) NOT NULL, name VARCHAR(255) NOT NULL, email VARCHAR(255) NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_427C1C7F4C3FA511 ON organizations (regon)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_427C1C7FE7927C74 ON organizations (email)');
        $this->addSql('CREATE TABLE room_statuses (id UUID NOT NULL, status VARCHAR(20) NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, room_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_EB16DED554177093 ON room_statuses (room_id)');
        $this->addSql('CREATE TABLE rooms (id UUID NOT NULL, room_name VARCHAR(100) NOT NULL, capacity INT NOT NULL, size DOUBLE PRECISION NOT NULL, location VARCHAR(255) NOT NULL, access VARCHAR(50) NOT NULL, description TEXT DEFAULT NULL, lighting VARCHAR(100) DEFAULT NULL, air_conditioning JSON DEFAULT NULL, image_path VARCHAR(255) DEFAULT NULL, organization_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_7CA11A9632C8A3DE ON rooms (organization_id)');
        $this->addSql('CREATE TABLE users (id UUID NOT NULL, username VARCHAR(180) NOT NULL, roles JSON NOT NULL, password VARCHAR(255) NOT NULL, first_name VARCHAR(255) NOT NULL, last_name VARCHAR(255) NOT NULL, email VARCHAR(100) NOT NULL, phone VARCHAR(20) NOT NULL, reset_token VARCHAR(64) DEFAULT NULL, reset_token_expires_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, organization_id UUID NOT NULL, PRIMARY KEY (id))');
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
        $this->addSql('ALTER TABLE room_statuses DROP CONSTRAINT FK_EB16DED554177093');
        $this->addSql('ALTER TABLE rooms DROP CONSTRAINT FK_7CA11A9632C8A3DE');
        $this->addSql('ALTER TABLE users DROP CONSTRAINT FK_1483A5E932C8A3DE');
        $this->addSql('ALTER TABLE user_favorite_rooms DROP CONSTRAINT FK_A1ACEEEBA76ED395');
        $this->addSql('ALTER TABLE user_favorite_rooms DROP CONSTRAINT FK_A1ACEEEB54177093');
        $this->addSql('DROP TABLE bookings');
        $this->addSql('DROP TABLE booking_participants');
        $this->addSql('DROP TABLE equipment');
        $this->addSql('DROP TABLE organizations');
        $this->addSql('DROP TABLE room_statuses');
        $this->addSql('DROP TABLE rooms');
        $this->addSql('DROP TABLE users');
        $this->addSql('DROP TABLE user_favorite_rooms');
    }
}
