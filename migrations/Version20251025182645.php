<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251025182645 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE meeting (id SERIAL NOT NULL, host_id INT NOT NULL, participants_id INT DEFAULT NULL, title VARCHAR(255) NOT NULL, description TEXT DEFAULT NULL, type VARCHAR(50) NOT NULL, scheduled_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, duration INT DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, status VARCHAR(20) NOT NULL, password VARCHAR(255) DEFAULT NULL, max_participants INT DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_F515E1391FB8D185 ON meeting (host_id)');
        $this->addSql('CREATE INDEX IDX_F515E139838709D5 ON meeting (participants_id)');
        $this->addSql('ALTER TABLE meeting ADD CONSTRAINT FK_F515E1391FB8D185 FOREIGN KEY (host_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE meeting ADD CONSTRAINT FK_F515E139838709D5 FOREIGN KEY (participants_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE meeting DROP CONSTRAINT FK_F515E1391FB8D185');
        $this->addSql('ALTER TABLE meeting DROP CONSTRAINT FK_F515E139838709D5');
        $this->addSql('DROP TABLE meeting');
    }
}
