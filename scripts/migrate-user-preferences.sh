#!/bin/bash
# Data migration wrapper: Copy user preferences from backend to global-service
#
# This script migrates user_preferences data from the backend's mint_db to
# global-service's global_db.global_schema for Phase 4.6 of the Global Service
# API Gateway migration.
#
# Tables migrated:
# - user_preferences
#
# Usage:
#     # From infra directory:
#     ./scripts/migrate-user-preferences.sh
#
#     # With dry-run (no changes):
#     ./scripts/migrate-user-preferences.sh --dry-run
#
#     # Skip Keycloak username-to-UUID resolution:
#     ./scripts/migrate-user-preferences.sh --skip-resolve
#
#     # From anywhere:
#     cd infra && ./scripts/migrate-user-preferences.sh

set -e

# Get the directory where this script is located
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
INFRA_DIR="$(dirname "$SCRIPT_DIR")"

# Change to infra directory if not already there
cd "$INFRA_DIR"

echo "========================================"
echo "User Preferences Data Migration"
echo "========================================"
echo ""

# Execute the Python migration script inside global-service container
docker compose -f docker-compose.yml -f docker-compose.local.yml exec \
    global-service python scripts/migrate_user_preferences.py "$@"
