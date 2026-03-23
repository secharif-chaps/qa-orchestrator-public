#!/bin/bash
# Configure LLM environment variables in .env.
# Tries: env vars → .env values → interactive prompt.
# Usage: bash infra/scripts/setup-llm.sh

set -euo pipefail

ENV_FILE=".env"

if ! [ -f "$ENV_FILE" ]; then
  echo "❌ $ENV_FILE not found. Run from monorepo root."
  exit 1
fi

# Check if already configured
LLM_KEY=$(grep '^LLM_API_KEY=' "$ENV_FILE" 2>/dev/null | cut -d= -f2- || true)
LLM_URL=$(grep '^LLM_BASE_URL=' "$ENV_FILE" 2>/dev/null | cut -d= -f2- || true)

if [ -n "$LLM_KEY" ] && [ "$LLM_KEY" != "changeme" ] && \
   [ -n "$LLM_URL" ] && [ "$LLM_URL" != "changeme" ]; then
  echo "ℹ️  LLM already configured in $ENV_FILE, skipping"
  exit 0
fi

echo ""
echo "🤖 LLM configuration required (OpenAI-compatible endpoint)"
echo "   Set LLM_BASE_URL and LLM_API_KEY for your provider."
echo "   (Credentials available in Passbolt — search for 'LLM Gateway - chapsmind-env-dev')"
echo ""

API_KEY=""
BASE_URL=""

# 1. Try env vars (e.g. CI or pre-configured shell)
if [ -n "${LLM_API_KEY:-}" ] && [ "${LLM_API_KEY:-}" != "changeme" ] && \
   [ -n "${LLM_BASE_URL:-}" ] && [ "${LLM_BASE_URL:-}" != "changeme" ]; then
  API_KEY="$LLM_API_KEY"
  BASE_URL="$LLM_BASE_URL"
fi

# 2. If missing, prompt interactively
if [ -z "$API_KEY" ] || [ -z "$BASE_URL" ]; then
  if [ -t 0 ]; then
    read -rp "LLM_BASE_URL: " BASE_URL
    read -rp "LLM_API_KEY: " API_KEY
  else
    echo "⚠️  LLM_API_KEY / LLM_BASE_URL not set."
    echo "   Export them as environment variables or run interactively."
    echo "   AI features (chat, agents) will not work until configured."
    exit 0
  fi
fi

# 3. Write to .env
if grep -q '^LLM_API_KEY=' "$ENV_FILE"; then
  sed -i.bak "s|^LLM_API_KEY=.*|LLM_API_KEY=${API_KEY}|" "$ENV_FILE"
else
  echo "LLM_API_KEY=${API_KEY}" >> "$ENV_FILE"
fi

if grep -q '^LLM_BASE_URL=' "$ENV_FILE"; then
  sed -i.bak "s|^LLM_BASE_URL=.*|LLM_BASE_URL=${BASE_URL}|" "$ENV_FILE"
else
  echo "LLM_BASE_URL=${BASE_URL}" >> "$ENV_FILE"
fi

rm -f .env.bak
echo "✅ LLM configured (LLM_BASE_URL=${BASE_URL})"
