<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250912152551 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add indexes on source name and primary_domain for search optimization';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
                CREATE INDEX IDX_5F8A7F735E237E06 ON source (name)
            SQL);
        $this->addSql(<<<'SQL'
                CREATE INDEX IDX_5F8A7F735F5EA6BB ON source (primary_domain)
            SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
                DROP INDEX IDX_5F8A7F735E237E06
            SQL);
        $this->addSql(<<<'SQL'
                DROP INDEX IDX_5F8A7F735F5EA6BB
            SQL);
    }
}
