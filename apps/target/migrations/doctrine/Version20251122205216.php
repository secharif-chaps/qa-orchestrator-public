<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251122205216 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Flatten classification data into AnalysisResult with embedded DeepSearchReadiness';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE analysis_result ADD primary_classification_confidence INT DEFAULT NULL');
        $this->addSql('ALTER TABLE analysis_result ADD secondary_classification_types JSON DEFAULT NULL');
        $this->addSql('ALTER TABLE analysis_result ADD classification_keywords JSON DEFAULT NULL');
        $this->addSql('ALTER TABLE analysis_result ADD classification_detected_entities JSON DEFAULT NULL');
        $this->addSql('ALTER TABLE analysis_result ADD classification_user_objective JSON DEFAULT NULL');
        $this->addSql('ALTER TABLE analysis_result ADD source_suggestions JSON DEFAULT NULL');
        $this->addSql('ALTER TABLE analysis_result ADD actor_suggestions JSON DEFAULT NULL');
        $this->addSql('ALTER TABLE analysis_result ADD deep_search_readiness_ready BOOLEAN NOT NULL');
        $this->addSql('ALTER TABLE analysis_result ADD deep_search_readiness_reason JSON NOT NULL');
        $this->addSql('ALTER TABLE analysis_result ADD deep_search_readiness_suggested_search_queries JSON NOT NULL');
        $this->addSql('ALTER TABLE analysis_result RENAME COLUMN monitoring_type TO primary_classification_type');
        $this->addSql(
            'ALTER TABLE analysis_result RENAME COLUMN classification_result TO primary_classification_justification'
        );
        $this->addSql('ALTER TABLE analysis_result ALTER content DROP NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE analysis_result ADD classification_result JSON DEFAULT NULL');
        $this->addSql('ALTER TABLE analysis_result DROP primary_classification_confidence');
        $this->addSql('ALTER TABLE analysis_result DROP primary_classification_justification');
        $this->addSql('ALTER TABLE analysis_result DROP secondary_classification_types');
        $this->addSql('ALTER TABLE analysis_result DROP classification_keywords');
        $this->addSql('ALTER TABLE analysis_result DROP classification_detected_entities');
        $this->addSql('ALTER TABLE analysis_result DROP classification_user_objective');
        $this->addSql('ALTER TABLE analysis_result DROP source_suggestions');
        $this->addSql('ALTER TABLE analysis_result DROP actor_suggestions');
        $this->addSql('ALTER TABLE analysis_result DROP deep_search_readiness_ready');
        $this->addSql('ALTER TABLE analysis_result DROP deep_search_readiness_reason');
        $this->addSql('ALTER TABLE analysis_result DROP deep_search_readiness_suggested_search_queries');
        $this->addSql('ALTER TABLE analysis_result RENAME COLUMN primary_classification_type TO monitoring_type');
        $this->addSql('ALTER TABLE analysis_result ALTER content SET NOT NULL');
    }
}
