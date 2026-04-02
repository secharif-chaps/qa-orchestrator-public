<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Add monitoring type classification fields to watch_file table.
 */
final class Version20250806120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add monitoring type classification fields to watch_file table';
    }

    public function up(Schema $schema): void
    {
        // Add new fields for monitoring type classification results
        $this->addSql('ALTER TABLE watch_file ADD monitoring_type_confidence INTEGER DEFAULT NULL');
        $this->addSql('ALTER TABLE watch_file ADD monitoring_type_justification TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE watch_file ADD secondary_monitoring_types JSONB DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // Remove the added fields
        $this->addSql('ALTER TABLE watch_file DROP monitoring_type_confidence');
        $this->addSql('ALTER TABLE watch_file DROP monitoring_type_justification');
        $this->addSql('ALTER TABLE watch_file DROP secondary_monitoring_types');
    }
}
