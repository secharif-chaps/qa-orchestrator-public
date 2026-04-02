<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20251020113621 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create document_validation table for manual validation tracking';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            'CREATE TABLE document_validation (id UUID NOT NULL, document_id UUID NOT NULL, action VARCHAR(255) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, user_id UUID NOT NULL, PRIMARY KEY (id))'
        );
        $this->addSql('CREATE INDEX IDX_B76FF038A76ED395 ON document_validation (user_id)');
        $this->addSql('CREATE INDEX IDX_B76FF038C33F78378B8E8428 ON document_validation (document_id, created_at)');
        $this->addSql(
            'ALTER TABLE document_validation ADD CONSTRAINT FK_B76FF038A76ED395 FOREIGN KEY (user_id) REFERENCES "user" (id) NOT DEFERRABLE'
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE document_validation DROP CONSTRAINT FK_B76FF038A76ED395');
        $this->addSql('DROP TABLE document_validation');
    }
}
