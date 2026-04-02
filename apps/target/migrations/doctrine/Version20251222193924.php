<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251222193924 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add geographic_scope, classification_topics to AnalysisResult and change classification_user_objective to VARCHAR';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE analysis_result ADD geographic_scope VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE analysis_result ADD classification_topics JSON DEFAULT NULL');
        $this->addSql('ALTER TABLE analysis_result ALTER classification_user_objective TYPE VARCHAR(1000)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE analysis_result DROP geographic_scope');
        $this->addSql('ALTER TABLE analysis_result DROP classification_topics');
        $this->addSql('ALTER TABLE analysis_result ALTER classification_user_objective TYPE JSON');
    }
}
