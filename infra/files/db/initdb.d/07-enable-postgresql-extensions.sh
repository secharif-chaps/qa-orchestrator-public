#!/usr/bin/env bash

set -euo pipefail
IFS=$'\n\t'

log() { echo "[INFO] $*"; }

log "Enabling PostgreSQL extensions..."

# Enable unaccent extension on target_db (used for accent-insensitive text search)
psql -v ON_ERROR_STOP=1 --username "$POSTGRES_USER" --dbname "target_db" <<-EOSQL
    CREATE EXTENSION IF NOT EXISTS unaccent;
EOSQL

log "PostgreSQL extensions enabled successfully."
