<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20251218100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add reference_subject_llm column to watch_file table for LLM validation';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE watch_file ADD reference_subject_llm TEXT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE watch_file DROP COLUMN reference_subject_llm');
    }
}
