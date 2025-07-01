-- Create Keycloak database and user
CREATE USER keycloak WITH PASSWORD '!ChangeMe!';
CREATE DATABASE keycloak OWNER keycloak;
GRANT ALL PRIVILEGES ON DATABASE keycloak TO keycloak;