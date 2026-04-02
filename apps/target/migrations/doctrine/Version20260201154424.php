<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Change addedByMessage foreign key from message_content to message.
 *
 * This fixes a bug where the userMessageId sent by the backend was a Message.id
 * but the handlers were trying to load it as a MessageContent.id, resulting in
 * the addedByMessage relationship never being set.
 *
 * After running this migration, use the recovery command to restore missing links:
 * php bin/console app:recover-message-links --dry-run
 * php bin/console app:recover-message-links
 */
final class Version20260201154424 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Change addedByMessage FK from message_content to message for source, strategic_question, and watch_file_actor tables';
    }

    public function up(Schema $schema): void
    {
        $tables = ['source', 'strategic_question', 'watch_file_actor'];

        // Step 1: Drop existing FK constraints
        $this->addSql('ALTER TABLE source DROP CONSTRAINT IF EXISTS fk_5f8a7f73fcd7352b');
        $this->addSql('ALTER TABLE strategic_question DROP CONSTRAINT IF EXISTS fk_23e8b870fcd7352b');
        $this->addSql('ALTER TABLE watch_file_actor DROP CONSTRAINT IF EXISTS fk_bd7fa950fcd7352b');

        // Step 2: Migrate data - convert message_content.id to message.id
        // For each table, update added_by_message_id to the corresponding message.id
        // if the current value is a message_content.id
        foreach ($tables as $table) {
            // Convert message_content.id references to message.id references
            $this->addSql(<<<SQL
                UPDATE {$table} t
                SET added_by_message_id = mc.message_id
                FROM message_content mc
                WHERE t.added_by_message_id = mc.id
                  AND t.added_by_message_id IS NOT NULL
                SQL);

            // Set to NULL any references that don't exist in message table
            // (orphaned references that were neither valid message nor message_content IDs)
            $this->addSql(<<<SQL
                UPDATE {$table} t
                SET added_by_message_id = NULL
                WHERE t.added_by_message_id IS NOT NULL
                  AND NOT EXISTS (
                    SELECT 1 FROM message m WHERE m.id = t.added_by_message_id
                  )
                SQL);
        }

        // Step 3: Add new FK constraints pointing to message table
        $this->addSql(
            'ALTER TABLE source ADD CONSTRAINT FK_5F8A7F73FCD7352B FOREIGN KEY (added_by_message_id) REFERENCES message (id) NOT DEFERRABLE'
        );
        $this->addSql(
            'ALTER TABLE strategic_question ADD CONSTRAINT FK_23E8B870FCD7352B FOREIGN KEY (added_by_message_id) REFERENCES message (id) NOT DEFERRABLE'
        );
        $this->addSql(
            'ALTER TABLE watch_file_actor ADD CONSTRAINT FK_BD7FA950FCD7352B FOREIGN KEY (added_by_message_id) REFERENCES message (id) NOT DEFERRABLE'
        );
    }

    public function down(Schema $schema): void
    {
        // WARNING: Rollback cannot fully restore the original message_content references
        // because the mapping from message to message_content is one-to-many.
        // After rollback, added_by_message_id will be set to NULL for all affected rows.

        $tables = ['source', 'strategic_question', 'watch_file_actor'];

        // Step 1: Drop new FK constraints
        $this->addSql('ALTER TABLE source DROP CONSTRAINT IF EXISTS FK_5F8A7F73FCD7352B');
        $this->addSql('ALTER TABLE strategic_question DROP CONSTRAINT IF EXISTS FK_23E8B870FCD7352B');
        $this->addSql('ALTER TABLE watch_file_actor DROP CONSTRAINT IF EXISTS FK_BD7FA950FCD7352B');

        // Step 2: Set added_by_message_id to NULL since we can't restore the original
        // message_content references (one message has many message_contents)
        foreach ($tables as $table) {
            $this->addSql("UPDATE {$table} SET added_by_message_id = NULL WHERE added_by_message_id IS NOT NULL");
        }

        // Step 3: Restore old FK constraints pointing to message_content table
        $this->addSql(
            'ALTER TABLE source ADD CONSTRAINT fk_5f8a7f73fcd7352b FOREIGN KEY (added_by_message_id) REFERENCES message_content (id) NOT DEFERRABLE INITIALLY IMMEDIATE'
        );
        $this->addSql(
            'ALTER TABLE strategic_question ADD CONSTRAINT fk_23e8b870fcd7352b FOREIGN KEY (added_by_message_id) REFERENCES message_content (id) NOT DEFERRABLE INITIALLY IMMEDIATE'
        );
        $this->addSql(
            'ALTER TABLE watch_file_actor ADD CONSTRAINT fk_bd7fa950fcd7352b FOREIGN KEY (added_by_message_id) REFERENCES message_content (id) NOT DEFERRABLE INITIALLY IMMEDIATE'
        );
    }
}
