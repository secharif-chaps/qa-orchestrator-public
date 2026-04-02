<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

class Version20260326153218 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add organisation_id FK on actor, watch_file, watch_file_activity + impersonator on watch_file_activity';
    }

    public function up(Schema $schema): void
    {
        // Step 1: Add columns as nullable to safely handle existing data
        $this->addSql('ALTER TABLE actor ADD organisation_id UUID DEFAULT NULL');
        $this->addSql('ALTER TABLE watch_file ADD organisation_id UUID DEFAULT NULL');
        $this->addSql('ALTER TABLE watch_file_activity ADD organisation_id UUID DEFAULT NULL');
        $this->addSql('ALTER TABLE watch_file_activity ADD impersonator_id UUID DEFAULT NULL');

        // Step 2: Backfill existing rows — associate them to the first available organisation.
        // Rows without a matching organisation will remain NULL and must be handled by a data migration.
        $this->addSql(<<<'SQL'
                UPDATE actor SET organisation_id = (SELECT id FROM organisation LIMIT 1)
                WHERE organisation_id IS NULL AND EXISTS (SELECT 1 FROM organisation LIMIT 1)
            SQL);
        $this->addSql(<<<'SQL'
                UPDATE watch_file SET organisation_id = (SELECT id FROM organisation LIMIT 1)
                WHERE organisation_id IS NULL AND EXISTS (SELECT 1 FROM organisation LIMIT 1)
            SQL);
        $this->addSql(<<<'SQL'
                UPDATE watch_file_activity SET organisation_id = (SELECT id FROM organisation LIMIT 1)
                WHERE organisation_id IS NULL AND EXISTS (SELECT 1 FROM organisation LIMIT 1)
            SQL);

        // Step 3: Add FK constraints (allow NULL during transition, tighten below if data is clean)
        $this->addSql(
            'ALTER TABLE actor ADD CONSTRAINT FK_447556F99E6B1585 FOREIGN KEY (organisation_id) REFERENCES organisation (id) NOT DEFERRABLE'
        );
        $this->addSql('CREATE INDEX IDX_447556F99E6B1585 ON actor (organisation_id)');
        $this->addSql(
            'ALTER TABLE watch_file ADD CONSTRAINT FK_DD7C96439E6B1585 FOREIGN KEY (organisation_id) REFERENCES organisation (id) NOT DEFERRABLE'
        );
        $this->addSql('CREATE INDEX IDX_DD7C96439E6B1585 ON watch_file (organisation_id)');
        $this->addSql(
            'ALTER TABLE watch_file_activity ADD CONSTRAINT FK_92C7B209E6B1585 FOREIGN KEY (organisation_id) REFERENCES organisation (id) NOT DEFERRABLE'
        );
        $this->addSql(
            'ALTER TABLE watch_file_activity ADD CONSTRAINT FK_92C7B20D1107CFF FOREIGN KEY (impersonator_id) REFERENCES "user" (id) NOT DEFERRABLE'
        );
        $this->addSql('CREATE INDEX IDX_92C7B20D1107CFF ON watch_file_activity (impersonator_id)');
        $this->addSql('CREATE INDEX IDX_92C7B209E6B1585 ON watch_file_activity (organisation_id)');

        // Step 4: Set NOT NULL now that existing rows are backfilled
        $this->addSql('ALTER TABLE actor ALTER COLUMN organisation_id SET NOT NULL');
        $this->addSql('ALTER TABLE watch_file ALTER COLUMN organisation_id SET NOT NULL');
        $this->addSql('ALTER TABLE watch_file_activity ALTER COLUMN organisation_id SET NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $hasData = $this->connection->fetchOne(<<<'SQL'
                SELECT EXISTS (
                    SELECT 1 FROM actor WHERE organisation_id IS NOT NULL
                    UNION ALL
                    SELECT 1 FROM watch_file WHERE organisation_id IS NOT NULL
                    UNION ALL
                    SELECT 1 FROM watch_file_activity WHERE organisation_id IS NOT NULL
                )
            SQL);
        $this->abortIf(
            (bool) $hasData,
            'Cannot rollback: actor, watch_file or watch_file_activity rows are already associated with an organisation. Rolling back would cause data loss.'
        );

        $this->addSql('ALTER TABLE actor DROP CONSTRAINT FK_447556F99E6B1585');
        $this->addSql('DROP INDEX IDX_447556F99E6B1585');
        $this->addSql('ALTER TABLE actor DROP organisation_id');
        $this->addSql('ALTER TABLE watch_file DROP CONSTRAINT FK_DD7C96439E6B1585');
        $this->addSql('DROP INDEX IDX_DD7C96439E6B1585');
        $this->addSql('ALTER TABLE watch_file DROP organisation_id');
        $this->addSql('ALTER TABLE watch_file_activity DROP CONSTRAINT FK_92C7B209E6B1585');
        $this->addSql('ALTER TABLE watch_file_activity DROP CONSTRAINT FK_92C7B20D1107CFF');
        $this->addSql('DROP INDEX IDX_92C7B20D1107CFF');
        $this->addSql('DROP INDEX IDX_92C7B209E6B1585');
        $this->addSql('ALTER TABLE watch_file_activity DROP organisation_id');
        $this->addSql('ALTER TABLE watch_file_activity DROP impersonator_id');
    }
}
