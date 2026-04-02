<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251205105313 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add cursor column for cursor-based pagination support (numeric microseconds since Unix epoch)';
    }

    public function up(Schema $schema): void
    {
        // Add the column as nullable first
        $this->addSql('ALTER TABLE message ADD cursor BIGINT DEFAULT NULL');

        // Populate from existing created_at values (convert to microseconds since Unix epoch)
        // EXTRACT(EPOCH FROM created_at) gives seconds since Unix epoch (including fractional seconds)
        // Multiply by 1000000 to convert to microseconds and cast to BIGINT
        $this->addSql('UPDATE message SET cursor = (EXTRACT(EPOCH FROM created_at) * 1000000)::BIGINT');

        // Make the column NOT NULL
        $this->addSql('ALTER TABLE message ALTER COLUMN cursor SET NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE message DROP cursor');
    }
}
