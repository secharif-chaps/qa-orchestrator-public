<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

class Version20251205162939 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create search_result_extraction table for storing entity extractions from search results';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE search_result_extraction (
            id UUID NOT NULL,
            search_result_id UUID NOT NULL,
            type VARCHAR(20) NOT NULL,
            data JSON NOT NULL,
            confidence_score DOUBLE PRECISION NOT NULL,
            extracted_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            PRIMARY KEY(id)
        )');
        $this->addSql('CREATE INDEX IDX_D2F714A3BCBCA6EB ON search_result_extraction (search_result_id)');
        $this->addSql(
            'ALTER TABLE search_result_extraction ADD CONSTRAINT FK_SEARCH_RESULT_EXTRACTION_SEARCH_RESULT FOREIGN KEY (search_result_id) REFERENCES search_result (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE'
        );
        $this->addSql(
            'ALTER TABLE search_result_extraction ADD CONSTRAINT CHK_TYPE CHECK (type IN (\'actor\', \'source\', \'topic\'))'
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE search_result_extraction');
    }
}
