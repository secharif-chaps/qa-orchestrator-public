-- Create Keycloak database (uses postgres superuser as per docker-compose config)
-- Note: Keycloak connects as postgres user (KC_DB_USERNAME=postgres)
CREATE DATABASE keycloak;
-- GRANT is a no-op when POSTGRES_USER already owns the DB
GRANT ALL PRIVILEGES ON DATABASE keycloak TO CURRENT_USER;