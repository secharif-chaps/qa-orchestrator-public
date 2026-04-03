-- Create Agent Memory database
-- Used by N8N workflows for LLM conversation memory storage
CREATE DATABASE agent_memory_db;
GRANT ALL PRIVILEGES ON DATABASE agent_memory_db TO postgres;
