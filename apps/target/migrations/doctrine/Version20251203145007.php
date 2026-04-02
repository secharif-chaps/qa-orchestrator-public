<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20251203145007 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add content field to SearchResult';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE search_result ADD content TEXT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE search_result DROP content');
    }
}
