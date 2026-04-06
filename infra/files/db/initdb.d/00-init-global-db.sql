-- Create Global Service database
-- Used by global-service microservice
CREATE DATABASE global_db;
-- GRANT is a no-op when POSTGRES_USER already owns the DB, but handles edge cases
GRANT ALL PRIVILEGES ON DATABASE global_db TO CURRENT_USER;