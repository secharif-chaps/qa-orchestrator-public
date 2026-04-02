<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251225220915 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add updatedAt and updatedBy columns to message table for tracking message updates (retry functionality)';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE message ADD updated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE message ADD updated_by_id UUID DEFAULT NULL');
        $this->addSql(
            'ALTER TABLE message ADD CONSTRAINT FK_B6BD307F896DBBDE FOREIGN KEY (updated_by_id) REFERENCES "user" (id)'
        );
        $this->addSql('CREATE INDEX IDX_B6BD307F896DBBDE ON message (updated_by_id)');
        $this->addSql('ALTER INDEX idx_message_status RENAME TO IDX_B6BD307F7B00651C');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE message DROP CONSTRAINT FK_B6BD307F896DBBDE');
        $this->addSql('DROP INDEX IDX_B6BD307F896DBBDE');
        $this->addSql('ALTER TABLE message DROP updated_at');
        $this->addSql('ALTER TABLE message DROP updated_by_id');
        $this->addSql('ALTER INDEX idx_b6bd307f7b00651c RENAME TO idx_message_status');
    }
}
