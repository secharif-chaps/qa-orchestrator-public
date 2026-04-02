<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250910141846 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Fix typo in watch_file.title_manually_set_by_user column';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE watch_file RENAME COLUMN title_manualy_set_by_user TO title_manually_set_by_user');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE watch_file RENAME COLUMN title_manually_set_by_user TO title_manualy_set_by_user');
    }
}
