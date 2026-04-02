<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20251113212455 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Change reference_subject column type from TEXT to JSON in watch_file table to make it translatable.';
    }

    public function up(Schema $schema): void
    {
        // Step 1: Add temporary JSON column
        $this->addSql('ALTER TABLE watch_file ADD COLUMN reference_subject_json JSON DEFAULT NULL');

        // Step 2: Migrate data with error handling using PL/pgSQL
        $this->addSql("
            DO \$\$
            DECLARE
                record RECORD;
            BEGIN
                FOR record IN SELECT id, reference_subject FROM watch_file WHERE reference_subject IS NOT NULL
                LOOP
                    BEGIN
                        -- Try to parse as JSON first (in case data is already JSON)
                        UPDATE watch_file
                        SET reference_subject_json = record.reference_subject::json
                        WHERE id = record.id;
                    EXCEPTION
                        WHEN invalid_text_representation THEN
                            -- If JSON parsing fails, wrap the TEXT value in a JSON object with both languages
                            UPDATE watch_file
                            SET reference_subject_json = jsonb_build_object('en', record.reference_subject, 'fr', record.reference_subject)
                            WHERE id = record.id;
                        WHEN OTHERS THEN
                            -- For any other error, log and set to NULL
                            RAISE NOTICE 'Error migrating reference_subject for watch_file.id=%: %', record.id, SQLERRM;
                            UPDATE watch_file
                            SET reference_subject_json = NULL
                            WHERE id = record.id;
                    END;
                END LOOP;
            END \$\$;
        ");

        // Step 3: Drop old column
        $this->addSql('ALTER TABLE watch_file DROP COLUMN reference_subject');

        // Step 4: Rename new column to original name
        $this->addSql('ALTER TABLE watch_file RENAME COLUMN reference_subject_json TO reference_subject');
    }

    public function down(Schema $schema): void
    {
        // Step 1: Add temporary TEXT column
        $this->addSql('ALTER TABLE watch_file ADD COLUMN reference_subject_text TEXT DEFAULT NULL');

        // Step 2: Extract text value from JSON with error handling
        $this->addSql("
            DO \$\$
            DECLARE
                record RECORD;
                json_value jsonb;
            BEGIN
                FOR record IN SELECT id, reference_subject FROM watch_file WHERE reference_subject IS NOT NULL
                LOOP
                    BEGIN
                        json_value := record.reference_subject::jsonb;

                        -- Try to extract 'en' value first, fallback to 'fr', then to raw JSON text
                        IF json_value ? 'en' THEN
                            UPDATE watch_file
                            SET reference_subject_text = json_value->>'en'
                            WHERE id = record.id;
                        ELSIF json_value ? 'fr' THEN
                            UPDATE watch_file
                            SET reference_subject_text = json_value->>'fr'
                            WHERE id = record.id;
                        ELSE
                            -- If no language keys found, convert entire JSON to text
                            UPDATE watch_file
                            SET reference_subject_text = record.reference_subject::text
                            WHERE id = record.id;
                        END IF;
                    EXCEPTION
                        WHEN OTHERS THEN
                            -- In case of any error, log and set to NULL
                            RAISE NOTICE 'Error downgrading reference_subject for watch_file.id=%: %', record.id, SQLERRM;
                            UPDATE watch_file
                            SET reference_subject_text = NULL
                            WHERE id = record.id;
                    END;
                END LOOP;
            END \$\$;
        ");

        // Step 3: Drop old JSON column
        $this->addSql('ALTER TABLE watch_file DROP COLUMN reference_subject');

        // Step 4: Rename new column to original name
        $this->addSql('ALTER TABLE watch_file RENAME COLUMN reference_subject_text TO reference_subject');
    }
}
