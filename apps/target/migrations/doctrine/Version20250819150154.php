<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250819150154 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add reference_subject column to watch_file table and clean up obsolete query column.';
    }

    public function up(Schema $schema): void
    {
        // Add reference_subject column (replaces query column)
        $this->addSql('ALTER TABLE watch_file ADD COLUMN IF NOT EXISTS reference_subject TEXT DEFAULT NULL');

        // Drop obsolete query column
        $this->addSql('ALTER TABLE watch_file DROP COLUMN IF EXISTS query');
    }

    public function down(Schema $schema): void
    {
        // Recreate query column for rollback
        $this->addSql('ALTER TABLE watch_file ADD COLUMN IF NOT EXISTS query TEXT NULL');

        // Remove reference_subject column
        $this->addSql('ALTER TABLE watch_file DROP COLUMN IF EXISTS reference_subject');
    }
}
