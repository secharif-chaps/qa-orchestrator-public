<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

class Version20260401100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Remove deprecated first_crawl and stream_delay from source parameters JSON column';
    }

    public function up(Schema $schema): void
    {
        // first_crawl was already cleaned via manual SQL on production,
        // but may still exist on other environments (staging, dev, local)
        $this->addSql(<<<'SQL'
                UPDATE source
                SET parameters = (parameters::jsonb - 'first_crawl')::text::json
                WHERE jsonb_exists(parameters::jsonb, 'first_crawl')
            SQL);

        $this->addSql(<<<'SQL'
                UPDATE source
                SET parameters = (parameters::jsonb - 'stream_delay')::text::json
                WHERE jsonb_exists(parameters::jsonb, 'stream_delay')
            SQL);
    }

    public function down(Schema $schema): void
    {
        // Data migration — removed keys cannot be restored
    }
}
