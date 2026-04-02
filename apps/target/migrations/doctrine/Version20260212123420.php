<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260212123420 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create agent_execution table and add FK on conversation for agent execution tracking';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(
            'CREATE TABLE agent_execution (id UUID NOT NULL, execution_id VARCHAR(255) NOT NULL, command_name VARCHAR(255) NOT NULL, status VARCHAR(50) DEFAULT \'running\' NOT NULL, started_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, completed_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, metadata JSON DEFAULT NULL, PRIMARY KEY (id))'
        );
        $this->addSql('ALTER TABLE conversation ADD agent_execution_id UUID DEFAULT NULL');
        $this->addSql(
            'ALTER TABLE conversation ADD CONSTRAINT FK_8A8E26E9215EDA8F FOREIGN KEY (agent_execution_id) REFERENCES agent_execution (id) ON DELETE SET NULL NOT DEFERRABLE'
        );
        $this->addSql('CREATE UNIQUE INDEX UNIQ_8A8E26E9215EDA8F ON conversation (agent_execution_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE agent_execution');
        $this->addSql('ALTER TABLE conversation DROP CONSTRAINT FK_8A8E26E9215EDA8F');
        $this->addSql('DROP INDEX UNIQ_8A8E26E9215EDA8F');
        $this->addSql('ALTER TABLE conversation DROP agent_execution_id');
    }
}
