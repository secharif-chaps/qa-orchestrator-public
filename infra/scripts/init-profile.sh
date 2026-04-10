#!/bin/bash
# Activate a Docker Compose profile: persist it to .env, start its services, run migrations.
# Usage: bash infra/scripts/init-profile.sh <profile>
# Example: bash infra/scripts/init-profile.sh screen

set -euo pipefail

PROFILE="${1:-}"

KNOWN_PROFILES=$(grep -roh 'profiles: \[.*\]' infra/compose.yaml infra/compose.local.yaml 2>/dev/null \
  | sed 's/profiles: \[//;s/\]//' | tr ',' '\n' | sed 's/ //g' | sort -u | tr '\n' ' ' | sed 's/ $//')
if [ -z "$PROFILE" ] || ! echo "$KNOWN_PROFILES" | grep -qw "$PROFILE"; then
  echo "Usage: bash infra/scripts/init-profile.sh <profile>"
  echo "Available profiles: ${KNOWN_PROFILES// /, }"
  exit 1
fi

# ─── 1. Persist profile to .env ──────────────────────

CURRENT_PROFILES=$(grep '^COMPOSE_PROFILES=' .env 2>/dev/null | cut -d= -f2 || true)
if echo "$CURRENT_PROFILES" | grep -qw "$PROFILE"; then
  echo "ℹ️  '${PROFILE}' already in COMPOSE_PROFILES"
else
  # Input is validated against KNOWN_PROFILES (no special chars possible) — sed with | delimiter is safe
  NEW_PROFILES="${CURRENT_PROFILES:+${CURRENT_PROFILES},}${PROFILE}"
  if grep -q '^COMPOSE_PROFILES=' .env; then
    sed -i "s|^COMPOSE_PROFILES=.*|COMPOSE_PROFILES=${NEW_PROFILES}|" .env
  else
    echo "COMPOSE_PROFILES=${NEW_PROFILES}" >> .env
  fi
  echo "✅ Set COMPOSE_PROFILES=${NEW_PROFILES}"
fi

# ─── 2. Start services for this profile ──────────────

echo ""
echo "🐳 Starting '${PROFILE}' services..."
docker compose --profile "${PROFILE}" up -d --build

# ─── 3. Run migrations ───────────────────────────────

echo ""
echo "🗃️  Running migrations for '${PROFILE}'..."
ALEMBIC_PROFILES="screen stream"
if echo "$ALEMBIC_PROFILES" | grep -qw "$PROFILE"; then
  docker compose exec "${PROFILE}" alembic upgrade head
else
  echo "  ℹ️  No alembic migrations for '${PROFILE}' — run migrations manually"
fi

echo ""
echo "✅ Profile '${PROFILE}' is ready."
