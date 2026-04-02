<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260326120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'TAR-1133 - Create quality_report table for document processing pipeline';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            'CREATE TABLE quality_report (
                id UUID NOT NULL,
                document_id UUID NOT NULL,
                overall_score DOUBLE PRECISION,
                decision VARCHAR(255) NOT NULL,
                decision_reason TEXT,
                signals JSONB NOT NULL,
                computed_at TIMESTAMP(0) WITH TIME ZONE NOT NULL,
                PRIMARY KEY (id)
            )'
        );
        $this->addSql('CREATE UNIQUE INDEX UNIQ_QUALITY_REPORT_DOCUMENT ON quality_report (document_id)');
        $this->addSql('CREATE INDEX IDX_QUALITY_REPORT_DECISION ON quality_report (decision)');
        $this->addSql('CREATE INDEX IDX_QUALITY_REPORT_SCORE ON quality_report (overall_score)');
        $this->addSql('CREATE INDEX IDX_QUALITY_REPORT_COMPUTED_AT ON quality_report (computed_at)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE quality_report');
    }
}
