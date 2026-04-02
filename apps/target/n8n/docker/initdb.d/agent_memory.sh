#!/usr/bin/env bash

set -euo pipefail
IFS=$'\n\t'

log() { echo "[INFO] $*"; }

# Ensure required environment variables are set
: "${POSTGRES_USER:?Missing POSTGRES_USER}"
: "${POSTGRES_DB:?Missing POSTGRES_DB}"
: "${AGENT_MEMORY_DB_NAME:?Missing AGENT_MEMORY_DB_NAME}"
: "${AGENT_MEMORY_DB_USER:?Missing AGENT_MEMORY_DB_USER}"
: "${AGENT_MEMORY_DB_PASSWORD:?Missing AGENT_MEMORY_DB_PASSWORD}"

log "Initializing agent memory database with dedicated user..."
psql -v ON_ERROR_STOP=1 --username "$POSTGRES_USER" --dbname "$POSTGRES_DB" <<-EOSQL
  -- Create the dedicated user for agent memory
  CREATE USER ${AGENT_MEMORY_DB_USER} WITH PASSWORD '${AGENT_MEMORY_DB_PASSWORD}';

  -- Create the agent memory database
  CREATE DATABASE ${AGENT_MEMORY_DB_NAME};

  -- Grant full access on the database to the dedicated user
  GRANT ALL PRIVILEGES ON DATABASE ${AGENT_MEMORY_DB_NAME} TO ${AGENT_MEMORY_DB_USER};

  -- Switch to the new database and grant schema privileges
  \c ${AGENT_MEMORY_DB_NAME}
  GRANT ALL ON SCHEMA public TO ${AGENT_MEMORY_DB_USER};
  ALTER DEFAULT PRIVILEGES IN SCHEMA public
    GRANT ALL ON TABLES TO ${AGENT_MEMORY_DB_USER};
  ALTER DEFAULT PRIVILEGES IN SCHEMA public
    GRANT ALL ON SEQUENCES TO ${AGENT_MEMORY_DB_USER};
EOSQL

log "Initialization complete."
