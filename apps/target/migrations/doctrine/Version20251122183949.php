<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251122183949 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Move classification data from WatchFile to AnalysisResult - add classification_result field to analysis_result, remove monitoring_type_confidence, monitoring_type_justification, and secondary_monitoring_types from watch_file';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE analysis_result ADD classification_result JSON DEFAULT NULL');
        $this->addSql('ALTER TABLE watch_file DROP monitoring_type_confidence');
        $this->addSql('ALTER TABLE watch_file DROP monitoring_type_justification');
        $this->addSql('ALTER TABLE watch_file DROP secondary_monitoring_types');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE analysis_result DROP classification_result');
        $this->addSql('ALTER TABLE watch_file ADD monitoring_type_confidence INT DEFAULT NULL');
        $this->addSql('ALTER TABLE watch_file ADD monitoring_type_justification TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE watch_file ADD secondary_monitoring_types JSON DEFAULT NULL');
    }
}
