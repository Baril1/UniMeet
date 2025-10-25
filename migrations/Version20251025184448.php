<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251025184448 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE meeting_user (meeting_id INT NOT NULL, user_id INT NOT NULL, PRIMARY KEY(meeting_id, user_id))');
        $this->addSql('CREATE INDEX IDX_61622E9B67433D9C ON meeting_user (meeting_id)');
        $this->addSql('CREATE INDEX IDX_61622E9BA76ED395 ON meeting_user (user_id)');
        $this->addSql('ALTER TABLE meeting_user ADD CONSTRAINT FK_61622E9B67433D9C FOREIGN KEY (meeting_id) REFERENCES meeting (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE meeting_user ADD CONSTRAINT FK_61622E9BA76ED395 FOREIGN KEY (user_id) REFERENCES "user" (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE meeting DROP CONSTRAINT fk_f515e139838709d5');
        $this->addSql('DROP INDEX idx_f515e139838709d5');
        $this->addSql('ALTER TABLE meeting DROP participants_id');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE meeting_user DROP CONSTRAINT FK_61622E9B67433D9C');
        $this->addSql('ALTER TABLE meeting_user DROP CONSTRAINT FK_61622E9BA76ED395');
        $this->addSql('DROP TABLE meeting_user');
        $this->addSql('ALTER TABLE meeting ADD participants_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE meeting ADD CONSTRAINT fk_f515e139838709d5 FOREIGN KEY (participants_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX idx_f515e139838709d5 ON meeting (participants_id)');
    }
}
