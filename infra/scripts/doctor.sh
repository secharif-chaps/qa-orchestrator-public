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
  DOCKER_V=$(docker --version 2>/dev/null | sed 's/[^0-9.]//g' | cut -d. -f1,2)
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
  NODE_MAJOR=$(echo "$NODE_V" | sed 's/v//' | cut -d. -f1)
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

# glab (GitLab CLI — used by pre-commit to lint .gitlab-ci.yml)
if command -v glab &> /dev/null; then
  GLAB_V=$(glab --version 2>/dev/null | head -1 | sed 's/glab version //')
  if glab auth status &> /dev/null; then
    ok "glab ${GLAB_V} (authenticated)"
  else
    warn "glab ${GLAB_V} installed but not authenticated — run: glab auth login"
  fi
else
  warn "glab not installed — CI lint in pre-commit will be skipped (install: https://gitlab.com/gitlab-org/cli)"
fi

# gum (used by `task profile` for the interactive selector — bash fallback otherwise)
if command -v gum &> /dev/null; then
  GUM_V=$(gum --version 2>/dev/null | head -1 | sed 's/gum version //')
  ok "gum ${GUM_V}"
else
  warn "gum not installed — 'task profile' will use bash fallback (install: https://github.com/charmbracelet/gum)"
fi

# ─── Connectivity ─────────────────────────────────────

echo ""
echo "🔍 Checking connectivity..."
echo ""

# HTTP
if curl -sf --connect-timeout 5 https://git.mediaspeech.com > /dev/null 2>&1; then
  ok "HTTPS access to git.mediaspeech.com"
else
  err "Cannot reach https://git.mediaspeech.com — check network/VPN"
fi

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

# Worktree context — mirrors the detection done by init.sh so doctor reports
# accurately when run from a worktree (different .env source, different hooks
# bootstrap requirement).
GIT_DIR=$(git rev-parse --git-dir 2>/dev/null || echo ".git")
GIT_COMMON=$(git rev-parse --git-common-dir 2>/dev/null || echo ".git")
IS_WORKTREE=false
MAIN_WT=""
if [ "$(cd "$GIT_DIR" 2>/dev/null && pwd -P)" != "$(cd "$GIT_COMMON" 2>/dev/null && pwd -P)" ]; then
  IS_WORKTREE=true
  MAIN_WT=$(git worktree list --porcelain | awk '/^worktree/{sub(/^worktree /, ""); print; exit}')
  ok "Running inside a git worktree (main: $MAIN_WT)"
fi

# .env
if [ -f .env ]; then
  ok ".env file exists"
  CHANGEME_VARS=$(grep '=changeme$' .env 2>/dev/null | sed 's/=.*//' || true)
  if [ -n "$CHANGEME_VARS" ]; then
    CHANGEME_COUNT=$(echo "$CHANGEME_VARS" | wc -l)
    warn "${CHANGEME_COUNT} variable(s) still set to 'changeme' in .env"
    echo "$CHANGEME_VARS" | while read -r var; do echo "     → $var"; done
  fi
elif [ "$IS_WORKTREE" = "true" ] && [ -n "$MAIN_WT" ] && [ -f "$MAIN_WT/.env" ]; then
  warn ".env missing — 'task init' will copy it from main worktree"
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
  # In a worktree, the husky wrapper directory `.husky/_` must also exist or
  # hooks are silently no-op (bootstrap copied by init.sh from the main worktree).
  if [ "$IS_WORKTREE" = "true" ] && [ ! -f .husky/_/h ]; then
    warn "Git hooks won't fire in this worktree — '.husky/_/h' missing (run 'task init')"
  else
    ok "Git pre-commit hook installed"
  fi
else
  warn "Git pre-commit hook not installed — run 'yarn install' from repo root"
fi

# ─── Keycloak realm (optional, only when running) ─────

echo ""
echo "🔍 Checking Keycloak realm configuration..."
echo ""

KEYCLOAK_URL="${KEYCLOAK_URL:-http://localhost:8080}"
KEYCLOAK_REALM="${KEYCLOAK_REALM:-chapsmind}"
KEYCLOAK_AUTH_REALM="${KEYCLOAK_AUTH_REALM:-master}"
KEYCLOAK_ADMIN_USER="${KEYCLOAK_ADMIN:-admin}"
KEYCLOAK_ADMIN_PASS="${KEYCLOAK_ADMIN_PASSWORD:-admin}"

if curl -sf --connect-timeout 2 "${KEYCLOAK_URL}/realms/${KEYCLOAK_REALM}/.well-known/openid-configuration" > /dev/null 2>&1; then
  # Invoke nested doctor without tripping our `set -e`. Its exit code (1 on errors) is
  # mapped back to our ERRORS counter so the summary below reflects the full state.
  set +e
  bash infra/scripts/doctor-keycloak.sh \
    --url "$KEYCLOAK_URL" \
    --realm "$KEYCLOAK_REALM" \
    --auth-realm "$KEYCLOAK_AUTH_REALM" \
    --user "$KEYCLOAK_ADMIN_USER" \
    --pass "$KEYCLOAK_ADMIN_PASS"
  KC_STATUS=$?
  set -e
  if [ "$KC_STATUS" -ne 0 ]; then
    ERRORS=$((ERRORS + 1))
  fi
else
  echo "ℹ️  Keycloak not reachable at ${KEYCLOAK_URL} — skipping realm inspection (run 'task up' first)"
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
