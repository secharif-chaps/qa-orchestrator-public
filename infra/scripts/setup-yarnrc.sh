#!/bin/bash
# Configure apps/front/.yarnrc.yml from template.
# Tries: env vars → interactive prompt.
# Usage: bash infra/scripts/setup-yarnrc.sh

set -euo pipefail

YARNRC="apps/front/.yarnrc.yml"
TEMPLATE="apps/front/.yarnrc.dist.yml"

if [ -f "$YARNRC" ] && ! grep -q '<OWLINT_' "$YARNRC"; then
  echo "ℹ️  $YARNRC already configured, skipping"
  exit 0
fi

echo "Configuring $YARNRC from template..."
echo "(Credentials available in Passbolt — search for 'CHAPSMIND_VUELLAR')"
echo ""

REGISTRY_URL=""
DEPLOY_KEY=""

# 1. Try env vars (e.g. CI or pre-configured shell)
if [ -n "${OWLINT_REGISTRY_URL:-}" ] && [ -n "${OWLINT_DEPLOY_KEY:-}" ]; then
  REGISTRY_URL="$OWLINT_REGISTRY_URL"
  DEPLOY_KEY="$OWLINT_DEPLOY_KEY"
fi

# 2. If missing, prompt interactively
if [ -z "$REGISTRY_URL" ] || [ "$REGISTRY_URL" = "changeme" ] || \
   [ -z "$DEPLOY_KEY" ] || [ "$DEPLOY_KEY" = "changeme" ]; then
  if [ -t 0 ]; then
    read -rp "OWLINT_REGISTRY_URL: " REGISTRY_URL
    read -rp "OWLINT_DEPLOY_KEY: " DEPLOY_KEY
  else
    echo "❌ OWLINT_REGISTRY_URL / OWLINT_DEPLOY_KEY not set."
    echo "   Export them as environment variables or run interactively."
    exit 1
  fi
fi

sed -e "s|<OWLINT_REGISTRY_URL>|${REGISTRY_URL}|g" \
    -e "s|<OWLINT_DEPLOY_KEY>|${DEPLOY_KEY}|g" \
    "$TEMPLATE" > "$YARNRC"
echo "✅ Created $YARNRC"