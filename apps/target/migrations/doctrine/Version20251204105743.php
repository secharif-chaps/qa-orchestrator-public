<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

class Version20251204105743 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add qualityScore, isSelected, and selectionRank fields to SearchResult';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE search_result ADD quality_score INT DEFAULT NULL');
        $this->addSql('ALTER TABLE search_result ADD is_selected BOOLEAN DEFAULT false NOT NULL');
        $this->addSql('ALTER TABLE search_result ADD selection_rank INT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE search_result DROP quality_score');
        $this->addSql('ALTER TABLE search_result DROP is_selected');
        $this->addSql('ALTER TABLE search_result DROP selection_rank');
    }
}
