<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20251014152017 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add document_seen_status table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            'CREATE TABLE document_seen_status (id UUID NOT NULL, document_id UUID NOT NULL, seen_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, user_id UUID NOT NULL, watch_file_id UUID NOT NULL, PRIMARY KEY (id))'
        );
        $this->addSql('CREATE INDEX IDX_75EA56C3A76ED395 ON document_seen_status (user_id)');
        $this->addSql('CREATE INDEX IDX_75EA56C36A35E6FF ON document_seen_status (watch_file_id)');
        $this->addSql(
            'CREATE UNIQUE INDEX UNIQ_75EA56C3A76ED395C33F7837 ON document_seen_status (user_id, document_id)'
        );
        $this->addSql(
            'ALTER TABLE document_seen_status ADD CONSTRAINT FK_75EA56C3A76ED395 FOREIGN KEY (user_id) REFERENCES "user" (id) NOT DEFERRABLE'
        );
        $this->addSql(
            'ALTER TABLE document_seen_status ADD CONSTRAINT FK_75EA56C36A35E6FF FOREIGN KEY (watch_file_id) REFERENCES watch_file (id) NOT DEFERRABLE'
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE document_seen_status DROP CONSTRAINT FK_75EA56C3A76ED395');
        $this->addSql('ALTER TABLE document_seen_status DROP CONSTRAINT FK_75EA56C36A35E6FF');
        $this->addSql('DROP TABLE document_seen_status');
    }
}
