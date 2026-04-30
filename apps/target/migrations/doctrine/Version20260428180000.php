<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Convert quality_report.decision_reason from TEXT to JSON (TranslatedText).
 *
 * Existing string reasons (in French) are folded into both `fr` and `en` keys
 * — the original messages were French sentences produced inside processors,
 * so we keep them on the French key and mirror them on `en` to satisfy the
 * TranslatedText invariant. Future reports written via QualityReportBuilder
 * will carry properly localized reasons emitted by halt processors.
 */
class Version20260428180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Convert quality_report.decision_reason TEXT → JSON for TranslatedText';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
                ALTER TABLE quality_report
                ALTER COLUMN decision_reason TYPE JSON
                USING (
                    CASE
                        WHEN decision_reason IS NULL THEN NULL
                        ELSE jsonb_build_object('fr', decision_reason, 'en', decision_reason)::json
                    END
                )
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
                ALTER TABLE quality_report
                ALTER COLUMN decision_reason TYPE TEXT
                USING (
                    CASE
                        WHEN decision_reason IS NULL THEN NULL
                        ELSE (decision_reason::jsonb ->> 'fr')
                    END
                )
            SQL);
    }
}
