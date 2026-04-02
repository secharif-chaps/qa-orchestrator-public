<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250904165750 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(
            'CREATE TABLE source_activity (id UUID NOT NULL, action_type VARCHAR(255) NOT NULL, action_data JSON NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, source_id UUID NOT NULL, user_id UUID NOT NULL, PRIMARY KEY (id))'
        );
        $this->addSql('CREATE INDEX IDX_EF543F88953C1C61 ON source_activity (source_id)');
        $this->addSql('CREATE INDEX IDX_EF543F88A76ED395 ON source_activity (user_id)');
        $this->addSql('CREATE INDEX IDX_EF543F888B8E8428 ON source_activity (created_at)');
        $this->addSql('CREATE INDEX IDX_EF543F88FA3FEC27 ON source_activity (action_type)');
        $this->addSql(
            'ALTER TABLE source_activity ADD CONSTRAINT FK_EF543F88953C1C61 FOREIGN KEY (source_id) REFERENCES source (id) NOT DEFERRABLE'
        );
        $this->addSql(
            'ALTER TABLE source_activity ADD CONSTRAINT FK_EF543F88A76ED395 FOREIGN KEY (user_id) REFERENCES "user" (id) NOT DEFERRABLE'
        );
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE source_activity DROP CONSTRAINT FK_EF543F88A76ED395');
        $this->addSql('ALTER TABLE source_activity DROP CONSTRAINT FK_EF543F88953C1C61');
        $this->addSql('DROP INDEX IDX_EF543F88FA3FEC27');
        $this->addSql('DROP INDEX IDX_EF543F888B8E8428');
        $this->addSql('DROP INDEX IDX_EF543F88A76ED395');
        $this->addSql('DROP INDEX IDX_EF543F88953C1C61');
        $this->addSql('DROP TABLE source_activity');
    }
}
