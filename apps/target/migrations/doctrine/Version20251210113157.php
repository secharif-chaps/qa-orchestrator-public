<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20251210113157 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Alter search_result.title column type from VARCHAR(255) to TEXT';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE search_result ALTER title TYPE TEXT');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE search_result ALTER title TYPE VARCHAR(255)');
    }
}
