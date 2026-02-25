-- Create Keycloak database (uses postgres superuser as per docker-compose config)
-- Note: Keycloak connects as postgres user (KC_DB_USERNAME=postgres)
CREATE DATABASE keycloak;
GRANT ALL PRIVILEGES ON DATABASE keycloak TO postgres;