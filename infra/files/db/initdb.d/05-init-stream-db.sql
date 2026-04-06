-- Create Stream application database
-- Used by the stream FastAPI service (multi-channel event distribution)
CREATE DATABASE stream_db;
-- GRANT is a no-op when POSTGRES_USER already owns the DB
GRANT ALL PRIVILEGES ON DATABASE stream_db TO CURRENT_USER;
