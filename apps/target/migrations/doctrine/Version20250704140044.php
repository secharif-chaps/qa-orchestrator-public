<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250704140044 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE source ADD status VARCHAR(255) DEFAULT 'active' NOT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE source ADD actor_id UUID DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE source DROP is_active
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE source ADD CONSTRAINT FK_5F8A7F7310DAF24A FOREIGN KEY (actor_id) REFERENCES actor (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_5F8A7F7310DAF24A ON source (actor_id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE watch_file_actor ADD status VARCHAR(255) DEFAULT 'active' NOT NULL
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE watch_file_actor DROP status
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE source DROP CONSTRAINT FK_5F8A7F7310DAF24A
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE source DROP CONSTRAINT FK_5F8A7F738BD2BBD1
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX IDX_5F8A7F7310DAF24A
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE source ADD is_active BOOLEAN NOT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE source DROP status
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE source DROP actor_id
        SQL);
    }
}
