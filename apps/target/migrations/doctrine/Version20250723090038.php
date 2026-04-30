<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250723090038 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
                ALTER TABLE message ADD created_by_id UUID DEFAULT NULL
            SQL);
        $this->addSql(<<<'SQL'
                ALTER TABLE message ADD CONSTRAINT FK_B6BD307FB03A8386 FOREIGN KEY (created_by_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE
            SQL);
        $this->addSql(<<<'SQL'
                CREATE INDEX IDX_B6BD307FB03A8386 ON message (created_by_id)
            SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
                ALTER TABLE message DROP CONSTRAINT FK_B6BD307FB03A8386
            SQL);
        $this->addSql(<<<'SQL'
                DROP INDEX IDX_B6BD307FB03A8386
            SQL);
        $this->addSql(<<<'SQL'
                ALTER TABLE message DROP created_by_id
            SQL);
    }
}
