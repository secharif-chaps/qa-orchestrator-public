<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Add status and retry_count fields to message table, and state field to conversation table.
 */
final class Version20251224002353 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add message status tracking (status, retry_count) and conversation state fields';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE message ADD status VARCHAR(50) DEFAULT 'sent' NOT NULL");
        $this->addSql('ALTER TABLE message ADD retry_count INT DEFAULT 0 NOT NULL');
        $this->addSql('CREATE INDEX idx_message_status ON message (status)');

        $this->addSql("ALTER TABLE conversation ADD state VARCHAR(50) DEFAULT 'idle' NOT NULL");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_message_status');
        $this->addSql('ALTER TABLE message DROP status');
        $this->addSql('ALTER TABLE message DROP retry_count');

        $this->addSql('ALTER TABLE conversation DROP state');
    }
}
