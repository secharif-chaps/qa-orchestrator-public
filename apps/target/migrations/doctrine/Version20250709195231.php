<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250709195231 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
                CREATE INDEX IDX_DD7C96435E237E06 ON watch_file (name)
            SQL);
        $this->addSql(<<<'SQL'
                CREATE INDEX IDX_DD7C96437B00651C ON watch_file (status)
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
                DROP INDEX IDX_DD7C96435E237E06
            SQL);
        $this->addSql(<<<'SQL'
                DROP INDEX IDX_DD7C96437B00651C
            SQL);
    }
}
