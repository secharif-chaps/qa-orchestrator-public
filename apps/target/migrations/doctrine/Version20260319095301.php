<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260319095301 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(
            'CREATE TABLE organisation (id UUID NOT NULL, name VARCHAR(255) NOT NULL, keycloak_id VARCHAR(255) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY (id))'
        );
        $this->addSql('CREATE UNIQUE INDEX UNIQ_E6E132B4491914B1 ON organisation (keycloak_id)');
        $this->addSql(
            'CREATE TABLE organisation_user (id UUID NOT NULL, role VARCHAR(255) NOT NULL, membership_type VARCHAR(255) NOT NULL, synced_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, organisation_id UUID NOT NULL, user_id UUID NOT NULL, PRIMARY KEY (id))'
        );
        $this->addSql('CREATE INDEX IDX_CFD7D6519E6B1585 ON organisation_user (organisation_id)');
        $this->addSql('CREATE INDEX IDX_CFD7D651A76ED395 ON organisation_user (user_id)');
        $this->addSql(
            'CREATE UNIQUE INDEX UNIQ_CFD7D6519E6B1585A76ED395 ON organisation_user (organisation_id, user_id)'
        );
        $this->addSql(
            'ALTER TABLE organisation_user ADD CONSTRAINT FK_CFD7D6519E6B1585 FOREIGN KEY (organisation_id) REFERENCES organisation (id) NOT DEFERRABLE'
        );
        $this->addSql(
            'ALTER TABLE organisation_user ADD CONSTRAINT FK_CFD7D651A76ED395 FOREIGN KEY (user_id) REFERENCES "user" (id) NOT DEFERRABLE'
        );
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE organisation_user DROP CONSTRAINT FK_CFD7D6519E6B1585');
        $this->addSql('ALTER TABLE organisation_user DROP CONSTRAINT FK_CFD7D651A76ED395');
        $this->addSql('DROP TABLE organisation');
        $this->addSql('DROP TABLE organisation_user');
    }
}
