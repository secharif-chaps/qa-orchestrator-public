#!/bin/bash
# Check that all prerequisites are installed and configured.
# Usage: bash infra/scripts/doctor.sh

set -euo pipefail

ERRORS=0
WARNINGS=0

err()  { echo "❌ $*"; ERRORS=$((ERRORS + 1)); }
warn() { echo "⚠️  $*"; WARNINGS=$((WARNINGS + 1)); }
ok()   { echo "✅ $*"; }

# ─── Tools ────────────────────────────────────────────

echo "🔍 Checking prerequisites..."
echo ""

# Docker
if command -v docker &> /dev/null; then
  DOCKER_V=$(docker --version 2>/dev/null | grep -oP '\d+\.\d+' | head -1)
  ok "Docker ${DOCKER_V}"
else
  err "Docker not installed"
fi

# Docker Compose
if docker compose version &> /dev/null; then
  ok "Docker Compose $(docker compose version --short 2>/dev/null)"
else
  err "Docker Compose not available"
fi

# Docker daemon
if docker info &> /dev/null; then
  ok "Docker daemon running"
else
  err "Docker daemon not running"
fi

# Node.js
if command -v node &> /dev/null; then
  NODE_V=$(node --version 2>/dev/null)
  NODE_MAJOR=$(echo "$NODE_V" | grep -oP '\d+' | head -1)
  if [ "$NODE_MAJOR" -ge 24 ]; then
    ok "Node.js ${NODE_V}"
  else
    warn "Node.js ${NODE_V} (>= 24 recommended)"
  fi
else
  err "Node.js not installed"
fi

# Corepack
if command -v corepack &> /dev/null; then
  ok "Corepack available"
else
  err "Corepack not available (run: corepack enable)"
fi

# jq
if command -v jq &> /dev/null; then
  ok "jq $(jq --version 2>/dev/null)"
else
  err "jq not installed"
fi

# ─── Connectivity ─────────────────────────────────────

echo ""
echo "🔍 Checking connectivity..."
echo ""

# SSH
if ssh -o ConnectTimeout=5 -o BatchMode=yes -o StrictHostKeyChecking=no -T -p 17890 git@git.mediaspeech.com 2>&1 | grep -qi "welcome\|success"; then
  ok "SSH access to git.mediaspeech.com"
else
  warn "SSH access to git.mediaspeech.com — could not verify (check manually)"
fi

# Docker registry
if grep -q "registry.git.mediaspeech.com" ~/.docker/config.json 2>/dev/null; then
  ok "GitLab Container Registry (credentials found)"
else
  warn "GitLab Container Registry — run: docker login registry.git.mediaspeech.com"
fi

# ─── Project configuration ────────────────────────────

echo ""
echo "🔍 Checking project configuration..."
echo ""

# .env
if [ -f .env ]; then
  ok ".env file exists"
  CHANGEME_VARS=$(grep '=changeme$' .env 2>/dev/null | sed 's/=.*//' || true)
  if [ -n "$CHANGEME_VARS" ]; then
    CHANGEME_COUNT=$(echo "$CHANGEME_VARS" | wc -l)
    warn "${CHANGEME_COUNT} variable(s) still set to 'changeme' in .env"
    echo "$CHANGEME_VARS" | while read -r var; do echo "     → $var"; done
  fi
else
  warn ".env missing — will be created by 'task init'"
fi

# .yarnrc.yml
if [ -f apps/front/.yarnrc.yml ] && ! grep -q '<OWLINT_' apps/front/.yarnrc.yml; then
  ok "apps/front/.yarnrc.yml configured"
else
  warn "apps/front/.yarnrc.yml not configured — will be set up by 'task init'"
fi

# Git hooks
HOOKS_PATH=$(git config core.hooksPath 2>/dev/null || true)
if [ -f .husky/pre-commit ] && echo "$HOOKS_PATH" | grep -q "\.husky"; then
  ok "Git pre-commit hook installed"
else
  warn "Git pre-commit hook not installed — run 'yarn install' from repo root"
fi

# ─── Summary ──────────────────────────────────────────

echo ""
echo "────────────────────────────────────────"
if [ "$ERRORS" -gt 0 ]; then
  echo "❌ ${ERRORS} error(s), ${WARNINGS} warning(s) — fix errors before running 'task init'"
  exit 1
elif [ "$WARNINGS" -gt 0 ]; then
  echo "⚠️  ${WARNINGS} warning(s) — 'task init' should handle most of these"
else
  echo "✅ All good! Run 'task init' to set up the project."
fi
