-- Create N8N workflow automation database
-- Used by target-n8n service for workflow data
CREATE DATABASE n8n_db;
-- GRANT is a no-op when POSTGRES_USER already owns the DB
GRANT ALL PRIVILEGES ON DATABASE n8n_db TO CURRENT_USER;
