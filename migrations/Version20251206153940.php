<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251206153940 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE bookings DROP CONSTRAINT fk_7a853c3554177093');
        $this->addSql('ALTER TABLE bookings ALTER room_id DROP NOT NULL');
        $this->addSql('ALTER TABLE bookings ADD CONSTRAINT FK_7A853C3554177093 FOREIGN KEY (room_id) REFERENCES rooms (id) ON DELETE SET NULL NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE bookings DROP CONSTRAINT FK_7A853C3554177093');
        $this->addSql('ALTER TABLE bookings ALTER room_id SET NOT NULL');
        $this->addSql('ALTER TABLE bookings ADD CONSTRAINT fk_7a853c3554177093 FOREIGN KEY (room_id) REFERENCES rooms (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }
}
