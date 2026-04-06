-- Create Agent Memory database
-- Used by N8N workflows for LLM conversation memory storage
CREATE DATABASE agent_memory_db;
-- GRANT is a no-op when POSTGRES_USER already owns the DB
GRANT ALL PRIVILEGES ON DATABASE agent_memory_db TO CURRENT_USER;
