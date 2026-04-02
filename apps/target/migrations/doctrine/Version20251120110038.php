<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20251120110038 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Rename collect_status to collect_status in source table and make user_id nullable in source_activity table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE source RENAME COLUMN collector_status TO collect_status');
        $this->addSql('ALTER TABLE source_activity ALTER user_id DROP NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE source RENAME COLUMN collect_status TO collector_status');
        $this->addSql('ALTER TABLE source_activity ALTER user_id SET NOT NULL');
    }
}
