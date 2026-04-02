#!/usr/bin/env bash

set -euo pipefail
IFS=$'\n\t'

log() { echo "[INFO] $*"; }

# Ensure required environment variables are set
: "${POSTGRES_USER:?Missing POSTGRES_USER}"
: "${POSTGRES_DB:?Missing POSTGRES_DB}"
: "${N8N_DB_USER:?Missing N8N_DB_USER}"
: "${N8N_DB_PASSWORD:?Missing N8N_DB_PASSWORD}"
: "${N8N_DB_NAME:?Missing N8N_DB_NAME}"

log "Initializing n8n database and user..."
psql -v ON_ERROR_STOP=1 --username "$POSTGRES_USER" <<-EOSQL
  -- Create the n8n role
  CREATE USER ${N8N_DB_USER} WITH PASSWORD '${N8N_DB_PASSWORD}';
  -- Create the n8n database
  CREATE DATABASE ${N8N_DB_NAME};
  -- Grant full access on the database
  GRANT ALL PRIVILEGES ON DATABASE ${N8N_DB_NAME} TO ${N8N_DB_USER};

  -- Switch to the new database and grant schema privileges
  \c ${N8N_DB_NAME}
  GRANT ALL ON SCHEMA public TO ${N8N_DB_USER};
  ALTER DEFAULT PRIVILEGES IN SCHEMA public
    GRANT ALL ON TABLES TO ${N8N_DB_USER};
  ALTER DEFAULT PRIVILEGES IN SCHEMA public
    GRANT ALL ON SEQUENCES TO ${N8N_DB_USER};
EOSQL

log "Initialization complete."
