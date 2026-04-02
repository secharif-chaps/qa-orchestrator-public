<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250625132022 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE actor (id UUID NOT NULL, label VARCHAR(255) NOT NULL, primary_domain VARCHAR(255) DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))
        SQL);
        $this->addSql(<<<'SQL'
            CREATE UNIQUE INDEX UNIQ_447556F9EA750E8 ON actor (label)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE UNIQUE INDEX UNIQ_447556F95F5EA6BB ON actor (primary_domain)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE analysis_result (id UUID NOT NULL, content TEXT NOT NULL, monitoring_type VARCHAR(50) DEFAULT NULL, metadata JSON NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, identified_needs JSON NOT NULL, entities JSON NOT NULL, temporal_scope JSON NOT NULL, monitoring_types JSON NOT NULL, strategic_questions JSON NOT NULL, suggested_approach JSON NOT NULL, confidence_score INT DEFAULT NULL, watch_file_id UUID NOT NULL, PRIMARY KEY(id))
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_A8566F986A35E6FF ON analysis_result (watch_file_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE conversation (id UUID NOT NULL, title VARCHAR(255) DEFAULT NULL, metadata JSON DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, language VARCHAR(2) DEFAULT 'en' NOT NULL, watch_file_id UUID NOT NULL, PRIMARY KEY(id))
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_8A8E26E96A35E6FF ON conversation (watch_file_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE message (id UUID NOT NULL, role VARCHAR(50) NOT NULL, metadata JSON DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, conversation_id UUID NOT NULL, PRIMARY KEY(id))
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_B6BD307F9AC0396 ON message (conversation_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE message_content (id UUID NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, message_id UUID NOT NULL, type VARCHAR(255) NOT NULL, content TEXT DEFAULT NULL, filename VARCHAR(255) DEFAULT NULL, mime_type VARCHAR(100) DEFAULT NULL, size INT DEFAULT NULL, path VARCHAR(255) DEFAULT NULL, function_name VARCHAR(255) DEFAULT NULL, parameters JSON DEFAULT NULL, result JSON DEFAULT NULL, metadata JSON DEFAULT NULL, PRIMARY KEY(id))
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_6E4D0AA5537A1329 ON message_content (message_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE search_query (id UUID NOT NULL, search_term TEXT NOT NULL, country VARCHAR(2) NOT NULL, language VARCHAR(2) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, strategic_question_id UUID NOT NULL, PRIMARY KEY(id))
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_10887602B07DE3B9 ON search_query (strategic_question_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE search_result (id UUID NOT NULL, title VARCHAR(255) NOT NULL, description TEXT NOT NULL, url VARCHAR(255) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, search_query_id UUID NOT NULL, PRIMARY KEY(id))
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_CA88AE0CFFC3C42C ON search_result (search_query_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE source (id UUID NOT NULL, name VARCHAR(255) NOT NULL, description JSON NOT NULL, type VARCHAR(255) NOT NULL, url VARCHAR(255) NOT NULL, primary_domain VARCHAR(255) NOT NULL, query VARCHAR(255) DEFAULT NULL, relevance JSON NOT NULL, parameters JSON DEFAULT NULL, is_active BOOLEAN NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, watch_file_id UUID NOT NULL, added_by_message_id UUID DEFAULT NULL, PRIMARY KEY(id))
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_5F8A7F736A35E6FF ON source (watch_file_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_5F8A7F73FCD7352B ON source (added_by_message_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE UNIQUE INDEX watch_file_source_uniq ON source (watch_file_id, type, url)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE strategic_question (id UUID NOT NULL, question JSON NOT NULL, context JSON NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, search_queries_generated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, search_queries_executed_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, watch_file_id UUID NOT NULL, PRIMARY KEY(id))
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_23E8B8706A35E6FF ON strategic_question (watch_file_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE "user" (id UUID NOT NULL, email VARCHAR(255) DEFAULT NULL, roles JSON NOT NULL, user_name VARCHAR(255) NOT NULL, first_name VARCHAR(255) DEFAULT NULL, last_name VARCHAR(255) DEFAULT NULL, PRIMARY KEY(id))
        SQL);
        $this->addSql(<<<'SQL'
            CREATE UNIQUE INDEX UNIQ_8D93D649E7927C74 ON "user" (email)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE user_favorite_watch_file (id UUID NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, user_id UUID NOT NULL, watch_file_id UUID NOT NULL, PRIMARY KEY(id))
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_E9741BCCA76ED395 ON user_favorite_watch_file (user_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_E9741BCC6A35E6FF ON user_favorite_watch_file (watch_file_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE UNIQUE INDEX UNIQ_E9741BCCA76ED3956A35E6FF ON user_favorite_watch_file (user_id, watch_file_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE watch_file (id UUID NOT NULL, name VARCHAR(255) NOT NULL, user_objective TEXT NOT NULL, query TEXT DEFAULT NULL, state VARCHAR(255) NOT NULL, monitoring_type VARCHAR(50) DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, status VARCHAR(255) DEFAULT 'draft' NOT NULL, created_by_id UUID DEFAULT NULL, PRIMARY KEY(id))
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_DD7C9643B03A8386 ON watch_file (created_by_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE watch_file_activity (id UUID NOT NULL, action_type VARCHAR(255) NOT NULL, action_data JSON NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, watch_file_id UUID NOT NULL, user_id UUID NOT NULL, PRIMARY KEY(id))
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_92C7B206A35E6FF ON watch_file_activity (watch_file_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_92C7B20A76ED395 ON watch_file_activity (user_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE watch_file_actor (id UUID NOT NULL, type VARCHAR(255) NOT NULL, score DOUBLE PRECISION NOT NULL, explanation JSON DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, watch_file_id UUID NOT NULL, actor_id UUID NOT NULL, added_by_message_id UUID DEFAULT NULL, PRIMARY KEY(id))
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_BD7FA9506A35E6FF ON watch_file_actor (watch_file_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_BD7FA95010DAF24A ON watch_file_actor (actor_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_BD7FA950FCD7352B ON watch_file_actor (added_by_message_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE watch_file_user (id UUID NOT NULL, role VARCHAR(255) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, watch_file_id UUID NOT NULL, user_id UUID NOT NULL, created_by_id UUID DEFAULT NULL, updated_by_id UUID DEFAULT NULL, PRIMARY KEY(id))
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_3EB310D56A35E6FF ON watch_file_user (watch_file_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_3EB310D5A76ED395 ON watch_file_user (user_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_3EB310D5B03A8386 ON watch_file_user (created_by_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_3EB310D5896DBBDE ON watch_file_user (updated_by_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE UNIQUE INDEX UNIQ_3EB310D56A35E6FFA76ED395 ON watch_file_user (watch_file_id, user_id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE analysis_result ADD CONSTRAINT FK_A8566F986A35E6FF FOREIGN KEY (watch_file_id) REFERENCES watch_file (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE conversation ADD CONSTRAINT FK_8A8E26E96A35E6FF FOREIGN KEY (watch_file_id) REFERENCES watch_file (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE message ADD CONSTRAINT FK_B6BD307F9AC0396 FOREIGN KEY (conversation_id) REFERENCES conversation (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE message_content ADD CONSTRAINT FK_6E4D0AA5537A1329 FOREIGN KEY (message_id) REFERENCES message (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE search_query ADD CONSTRAINT FK_10887602B07DE3B9 FOREIGN KEY (strategic_question_id) REFERENCES strategic_question (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE search_result ADD CONSTRAINT FK_CA88AE0CFFC3C42C FOREIGN KEY (search_query_id) REFERENCES search_query (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE source ADD CONSTRAINT FK_5F8A7F736A35E6FF FOREIGN KEY (watch_file_id) REFERENCES watch_file (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE source ADD CONSTRAINT FK_5F8A7F73FCD7352B FOREIGN KEY (added_by_message_id) REFERENCES message_content (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE strategic_question ADD CONSTRAINT FK_23E8B8706A35E6FF FOREIGN KEY (watch_file_id) REFERENCES watch_file (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE user_favorite_watch_file ADD CONSTRAINT FK_E9741BCCA76ED395 FOREIGN KEY (user_id) REFERENCES "user" (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE user_favorite_watch_file ADD CONSTRAINT FK_E9741BCC6A35E6FF FOREIGN KEY (watch_file_id) REFERENCES watch_file (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE watch_file ADD CONSTRAINT FK_DD7C9643B03A8386 FOREIGN KEY (created_by_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE watch_file_activity ADD CONSTRAINT FK_92C7B206A35E6FF FOREIGN KEY (watch_file_id) REFERENCES watch_file (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE watch_file_activity ADD CONSTRAINT FK_92C7B20A76ED395 FOREIGN KEY (user_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE watch_file_actor ADD CONSTRAINT FK_BD7FA9506A35E6FF FOREIGN KEY (watch_file_id) REFERENCES watch_file (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE watch_file_actor ADD CONSTRAINT FK_BD7FA95010DAF24A FOREIGN KEY (actor_id) REFERENCES actor (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE watch_file_actor ADD CONSTRAINT FK_BD7FA950FCD7352B FOREIGN KEY (added_by_message_id) REFERENCES message_content (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE watch_file_user ADD CONSTRAINT FK_3EB310D56A35E6FF FOREIGN KEY (watch_file_id) REFERENCES watch_file (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE watch_file_user ADD CONSTRAINT FK_3EB310D5A76ED395 FOREIGN KEY (user_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE watch_file_user ADD CONSTRAINT FK_3EB310D5B03A8386 FOREIGN KEY (created_by_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE watch_file_user ADD CONSTRAINT FK_3EB310D5896DBBDE FOREIGN KEY (updated_by_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE analysis_result DROP CONSTRAINT FK_A8566F986A35E6FF
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE conversation DROP CONSTRAINT FK_8A8E26E96A35E6FF
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE message DROP CONSTRAINT FK_B6BD307F9AC0396
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE message_content DROP CONSTRAINT FK_6E4D0AA5537A1329
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE search_query DROP CONSTRAINT FK_10887602B07DE3B9
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE search_result DROP CONSTRAINT FK_CA88AE0CFFC3C42C
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE source DROP CONSTRAINT FK_5F8A7F736A35E6FF
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE source DROP CONSTRAINT FK_5F8A7F73FCD7352B
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE strategic_question DROP CONSTRAINT FK_23E8B8706A35E6FF
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE user_favorite_watch_file DROP CONSTRAINT FK_E9741BCCA76ED395
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE user_favorite_watch_file DROP CONSTRAINT FK_E9741BCC6A35E6FF
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE watch_file DROP CONSTRAINT FK_DD7C9643B03A8386
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE watch_file_activity DROP CONSTRAINT FK_92C7B206A35E6FF
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE watch_file_activity DROP CONSTRAINT FK_92C7B20A76ED395
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE watch_file_actor DROP CONSTRAINT FK_BD7FA9506A35E6FF
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE watch_file_actor DROP CONSTRAINT FK_BD7FA95010DAF24A
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE watch_file_actor DROP CONSTRAINT FK_BD7FA950FCD7352B
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE watch_file_user DROP CONSTRAINT FK_3EB310D56A35E6FF
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE watch_file_user DROP CONSTRAINT FK_3EB310D5A76ED395
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE watch_file_user DROP CONSTRAINT FK_3EB310D5B03A8386
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE watch_file_user DROP CONSTRAINT FK_3EB310D5896DBBDE
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE actor
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE analysis_result
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE conversation
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE message
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE message_content
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE search_query
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE search_result
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE source
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE strategic_question
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE "user"
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE user_favorite_watch_file
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE watch_file
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE watch_file_activity
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE watch_file_actor
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE watch_file_user
        SQL);
    }
}
