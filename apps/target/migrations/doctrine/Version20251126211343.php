<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251126211343 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE strategic_question ADD monitoring_dimension VARCHAR(255) NOT NULL');
        $this->addSql('ALTER TABLE strategic_question ADD priority INT NOT NULL');
        $this->addSql('ALTER TABLE strategic_question ADD expected_output_type VARCHAR(255) NOT NULL');
        $this->addSql('ALTER TABLE strategic_question ADD added_by_message_id UUID DEFAULT NULL');
        $this->addSql(
            'ALTER TABLE strategic_question ADD CONSTRAINT FK_23E8B870FCD7352B FOREIGN KEY (added_by_message_id) REFERENCES message_content (id) NOT DEFERRABLE'
        );
        $this->addSql('CREATE INDEX IDX_23E8B870FCD7352B ON strategic_question (added_by_message_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE strategic_question DROP CONSTRAINT FK_23E8B870FCD7352B');
        $this->addSql('DROP INDEX IDX_23E8B870FCD7352B');
        $this->addSql('ALTER TABLE strategic_question DROP monitoring_dimension');
        $this->addSql('ALTER TABLE strategic_question DROP priority');
        $this->addSql('ALTER TABLE strategic_question DROP expected_output_type');
        $this->addSql('ALTER TABLE strategic_question DROP added_by_message_id');
    }
}
