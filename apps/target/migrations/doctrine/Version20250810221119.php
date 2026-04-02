<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250810221119 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
                CREATE TABLE collect_task (id UUID NOT NULL, provider_name VARCHAR(50) NOT NULL, provider_task_id VARCHAR(255) DEFAULT NULL, status VARCHAR(255) DEFAULT 'created' NOT NULL, configuration JSON NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, completed_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, source_id UUID NOT NULL, watch_file_id UUID NOT NULL, PRIMARY KEY(id))
            SQL);
        $this->addSql(<<<'SQL'
                CREATE INDEX IDX_ED266774953C1C61 ON collect_task (source_id)
            SQL);
        $this->addSql(<<<'SQL'
                CREATE INDEX IDX_ED2667746A35E6FF ON collect_task (watch_file_id)
            SQL);
        $this->addSql(<<<'SQL'
                ALTER TABLE collect_task ADD CONSTRAINT FK_ED266774953C1C61 FOREIGN KEY (source_id) REFERENCES source (id) NOT DEFERRABLE INITIALLY IMMEDIATE
            SQL);
        $this->addSql(<<<'SQL'
                ALTER TABLE collect_task ADD CONSTRAINT FK_ED2667746A35E6FF FOREIGN KEY (watch_file_id) REFERENCES watch_file (id) NOT DEFERRABLE INITIALLY IMMEDIATE
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
                ALTER TABLE collect_task DROP CONSTRAINT FK_ED266774953C1C61
            SQL);
        $this->addSql(<<<'SQL'
                ALTER TABLE collect_task DROP CONSTRAINT FK_ED2667746A35E6FF
            SQL);
        $this->addSql(<<<'SQL'
                DROP TABLE collect_task
            SQL);
    }
}
