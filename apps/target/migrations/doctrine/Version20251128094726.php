<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251128094726 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add queryType, rationale and searchTermHash fields to SearchQuery entity with unique constraint';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE search_query ADD query_type VARCHAR(50) NOT NULL DEFAULT \'general\'');
        $this->addSql('ALTER TABLE search_query ADD rationale TEXT NOT NULL DEFAULT \'\'');
        $this->addSql('ALTER TABLE search_query ADD search_term_hash VARCHAR(32) NOT NULL DEFAULT \'\'');
        $this->addSql('ALTER TABLE search_query ALTER COLUMN query_type DROP DEFAULT');
        $this->addSql('ALTER TABLE search_query ALTER COLUMN rationale DROP DEFAULT');
        $this->addSql('ALTER TABLE search_query ALTER COLUMN search_term_hash DROP DEFAULT');
        $this->addSql(
            'CREATE UNIQUE INDEX UNIQ_10887602ED7400385373C966D4DB71B5B07DE3B9 ON search_query (search_term_hash, country, language, strategic_question_id)'
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE search_query DROP query_type');
        $this->addSql('ALTER TABLE search_query DROP rationale');
        $this->addSql('ALTER TABLE search_query DROP search_term_hash');
        $this->addSql('DROP INDEX UNIQ_10887602ED7400385373C966D4DB71B5B07DE3B9');
    }
}
