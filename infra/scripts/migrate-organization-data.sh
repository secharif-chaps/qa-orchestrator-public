#!/bin/bash
# Data migration wrapper: Copy organization data from backend to global-service
#
# This script migrates data from the backend's screen_db to global-service's
# global_db.global_schema for Phase 2.5 of the Global Service API Gateway migration.
#
# Tables migrated:
# - organizations
# - token_transactions
# - organization_modules
# - organization_feature_flags
#
# Usage:
#     # From infra directory:
#     ./scripts/migrate-organization-data.sh
#
#     # With dry-run (no changes):
#     ./scripts/migrate-organization-data.sh --dry-run
#
#     # From anywhere:
#     cd infra && ./scripts/migrate-organization-data.sh

set -e

# Get the directory where this script is located
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
INFRA_DIR="$(dirname "$SCRIPT_DIR")"

# Change to infra directory if not already there
cd "$INFRA_DIR"

echo "========================================"
echo "Organization Data Migration"
echo "========================================"
echo ""

# Execute the Python migration script inside global-service container
docker compose exec \
    global-service python scripts/migrate_organization_data.py "$@"
