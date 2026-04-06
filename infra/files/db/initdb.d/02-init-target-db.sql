-- Create Target (Basil) application database
-- Used by the target PHP/Symfony service (dedicated DB on shared PG instance)
CREATE DATABASE target_db;
-- GRANT is a no-op when POSTGRES_USER already owns the DB
GRANT ALL PRIVILEGES ON DATABASE target_db TO CURRENT_USER;
