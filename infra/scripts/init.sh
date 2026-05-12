#!/bin/bash
# One-shot first-time setup for ChapsMind local development.
# Usage: bash infra/scripts/init.sh
#
# What it does:
#   1. Create .env from template
#   2. Auto-generate secrets (ENCRYPTION_KEY, INTERNAL_JWT_SECRET)
#   3. Configure frontend private registry (.yarnrc.yml)
#   4. Install frontend dependencies
#   5. Build and start all Docker services
#   6. Wait for Keycloak + init realm/clients/users
#   7. Run database migrations
#   8. Print URLs and test users

set -euo pipefail

# ─── Helpers ─────────────────────────────────────────

# Returns the TCP ports published by the local compose stack, one per line.
# Tries `docker compose config` first (profile-aware, requires .env loaded).
# Falls back to a static grep on the compose files when .env is not yet
# available — which is the case for the worktree guard below.
get_stack_ports() {
  local ports
  ports=$(docker compose config 2>/dev/null | sed -n 's/.*published: "\([0-9]*\)".*/\1/p' | sort -u)
  if [ -z "$ports" ]; then
    # Static fallback: parse short-form "host:container" mappings from compose
    # files. Used by the worktree guard, which runs before .env is created.
    ports=$(grep -hoE '"[0-9]+:[0-9]+"' infra/compose.yaml infra/compose.local.yaml 2>/dev/null \
      | tr -d '"' | cut -d: -f1 | sort -u)
  fi
  echo "$ports"
}

# ─── 0. Worktree detection ───────────────────────────

GIT_DIR=$(git rev-parse --git-dir 2>/dev/null || echo ".git")
GIT_COMMON=$(git rev-parse --git-common-dir 2>/dev/null || echo ".git")
IS_WORKTREE=false
MAIN_WT=""
if [ "$(cd "$GIT_DIR" 2>/dev/null && pwd -P)" != "$(cd "$GIT_COMMON" 2>/dev/null && pwd -P)" ]; then
  IS_WORKTREE=true
  # First entry in worktree list is always the original (main) checkout.
  # Use sub() to preserve paths containing spaces (awk $2 would truncate).
  MAIN_WT=$(git worktree list --porcelain | awk '/^worktree/{sub(/^worktree /, ""); print; exit}')
  echo "ℹ️  Running inside a git worktree"

  # Guard: require the stack to be down before initializing a worktree.
  # Each worktree needs its own stack (different source mounts) — two stacks
  # can't share the same published ports. Developer must explicitly down the
  # active stack first.
  BUSY_PORT=""
  for port in $(get_stack_ports); do
    if docker ps -q --filter "publish=$port" | grep -q .; then
      BUSY_PORT="$port"
      break
    fi
  done
  if [ -n "$BUSY_PORT" ]; then
    echo ""
    echo "❌ A stack is already running (port $BUSY_PORT bound)."
    echo "   Stop it first from the active workspace:"
    echo "     task down"
    echo "   Then re-run: task init"
    exit 1
  fi
fi

# ─── 1. Create .env ──────────────────────────────────

if [ ! -f .env ]; then
  if [ "$IS_WORKTREE" = "true" ] && [ -n "$MAIN_WT" ] && [ -f "$MAIN_WT/.env" ]; then
    cp "$MAIN_WT/.env" .env
    echo "✅ Copied .env from main worktree"
  else
    cp .env.example .env
    echo "✅ Created .env from .env.example"
  fi
else
  echo "ℹ️  .env already exists, skipping copy"
fi

# Load COMPOSE_FILE and other vars from .env
set -a
source .env
set +a

# ─── 1b. Select optional profiles ────────────────────

CURRENT_PROFILES="${COMPOSE_PROFILES:-}"
if [ -n "$CURRENT_PROFILES" ]; then
  echo "ℹ️  Active profiles: ${CURRENT_PROFILES}  (edit COMPOSE_PROFILES in .env to change)"
else
  KNOWN_PROFILES=$(grep -roh 'profiles: \[.*\]' infra/compose.yaml infra/compose.local.yaml 2>/dev/null \
    | sed 's/profiles: \[//;s/\]//' | tr ',' '\n' | sed 's/ //g' | sort -u | tr '\n' ' ' | sed 's/ $//')
  echo ""
  echo "🧩 Optional modules (space-separated, Enter to skip):"
  echo "   Available: ${KNOWN_PROFILES// /  }"
  read -r -p "   Profiles [none]: " PROFILES_INPUT
  if [ -n "$PROFILES_INPUT" ]; then
    # Validate each token against known profiles
    PROFILES_VALID=""
    PROFILES_UNKNOWN=""
    for token in $PROFILES_INPUT; do
      if echo "$KNOWN_PROFILES" | grep -qw "$token"; then
        PROFILES_VALID="${PROFILES_VALID:+${PROFILES_VALID},}${token}"
      else
        PROFILES_UNKNOWN="${PROFILES_UNKNOWN} ${token}"
      fi
    done
    if [ -n "$PROFILES_UNKNOWN" ]; then
      echo "❌ Unknown profile(s):${PROFILES_UNKNOWN}. Available: ${KNOWN_PROFILES// /, }"
      exit 1
    fi
    # Input is validated against KNOWN_PROFILES (no special chars possible) — sed with | delimiter is safe
    if grep -q '^COMPOSE_PROFILES=' .env; then
      sed -i "s|^COMPOSE_PROFILES=.*|COMPOSE_PROFILES=${PROFILES_VALID}|" .env
    else
      echo "COMPOSE_PROFILES=${PROFILES_VALID}" >> .env
    fi
    # Reload .env so docker compose picks up the new value
    set -a
    source .env
    set +a
    echo "✅ Set COMPOSE_PROFILES=${PROFILES_VALID}"
  else
    echo "ℹ️  No profiles selected — starting base stack only"
  fi
fi

# ─── 2. Auto-generate secrets ────────────────────────

if grep -q '^ENCRYPTION_KEY=changeme$' .env; then
  KEY=$(docker compose run --rm --no-deps -T --entrypoint python3 global-service -c "from cryptography.fernet import Fernet; print(Fernet.generate_key().decode())" 2>/dev/null) \
    || KEY=$(python3 -c "from cryptography.fernet import Fernet; print(Fernet.generate_key().decode())")
  sed -i.bak "s/^ENCRYPTION_KEY=changeme$/ENCRYPTION_KEY=${KEY}/" .env
  echo "✅ Auto-generated ENCRYPTION_KEY"
fi

if grep -q '^INTERNAL_JWT_SECRET=changeme$' .env; then
  SECRET=$(openssl rand -base64 32)
  sed -i.bak "s|^INTERNAL_JWT_SECRET=changeme$|INTERNAL_JWT_SECRET=${SECRET}|" .env
  echo "✅ Auto-generated INTERNAL_JWT_SECRET"
fi

if ! grep -q '^DEV_MODE=' .env; then
  sed -i 's|^# DEV_MODE=false|DEV_MODE=true|' .env
  echo "✅ Set DEV_MODE=true for local development"
fi

rm -f .env.bak

# ─── 2b. Configure LLM ─────────────────────────────

bash infra/scripts/setup-llm.sh

# ─── 3. Configure .yarnrc.yml ────────────────────────

bash infra/scripts/setup-yarnrc.sh

# ─── 4. Install dependencies ──────────────────────────

echo ""
echo "📦 Installing dev tools (husky, lint-staged, commitlint)..."
if [ "$IS_WORKTREE" = "true" ]; then
  # Docker can't follow the .git pointer file outside the mounted path, so
  # husky's prepare script fails silently and .husky/_ is never created.
  # Install with HUSKY=0 then copy the bootstrap from the main worktree.
  docker run --rm -w /app \
    -v "$(pwd):/app" \
    -e HUSKY=0 \
    node:24 \
    sh -c "corepack enable && yarn install"
  if [ -d "$MAIN_WT/.husky/_" ] && [ ! -f ".husky/_/h" ]; then
    # Copy contents (src/.) into a known dest dir so we never re-nest as
    # .husky/_/_ (which is what `cp -r src dest` does when dest exists).
    if [ -d .husky/_ ]; then
      echo "⚠️  Existing '.husky/_' will be merged with main worktree's hooks"
      echo "   (stale files may remain — delete '.husky/_' to fully refresh)."
    fi
    mkdir -p .husky/_ && cp -r "$MAIN_WT/.husky/_/." .husky/_/
    echo "✅ Git hooks initialized"
  elif [ ! -d "$MAIN_WT/.husky/_" ]; then
    echo "⚠️  Main worktree '.husky/_' missing — git hooks won't run in this worktree."
    echo "   Run 'task init' from $MAIN_WT first to bootstrap them, then re-run here."
  fi
else
  docker run --rm -w /app \
    -v "$(pwd):/app" \
    node:24 \
    sh -c "git config --global --add safe.directory /app && corepack enable && yarn install"
fi

echo ""
echo "📦 Installing frontend dependencies..."
docker run --rm -w /app \
  -v "$(pwd)/apps/front:/app" \
  node:24 \
  sh -c "corepack enable && yarn install"

# ─── 5. Check required ports are free ─────────────────

# Stop our own project first (if running) so ports are free for the check
if docker compose ps -q 2>/dev/null | grep -q .; then
  echo ""
  echo "🔄 Stopping existing containers..."
  docker compose down --remove-orphans
fi

BUSY_PORTS=""
for port in $(get_stack_ports); do
  if lsof -iTCP:"$port" -sTCP:LISTEN -P -n >/dev/null 2>&1; then
    PROCESS=$(lsof -iTCP:"$port" -sTCP:LISTEN -P -n 2>/dev/null | tail -1 | awk '{print $1}')
    BUSY_PORTS="${BUSY_PORTS}  → port ${port} (${PROCESS:-unknown})\n"
  fi
done

if [ -n "$BUSY_PORTS" ]; then
  echo "❌ Required ports are already in use:"
  echo -e "$BUSY_PORTS"
  echo "   Free these ports and re-run 'task init'."
  exit 1
fi

# ─── 6. Build and start services ─────────────────────

echo ""
echo "🐳 Building and starting services..."
docker compose up -d --build

# ─── 7. Wait for Keycloak + init ─────────────────────

echo ""
echo "⏳ Waiting for Keycloak to be ready (first start takes ~1-2 min for realm import)..."
KEYCLOAK_TIMEOUT=90
for i in $(seq 1 "$KEYCLOAK_TIMEOUT"); do
  if curl -sf http://localhost:8080/realms/master > /dev/null 2>&1; then
    echo -e "\r✅ Keycloak is ready (${i}s)                    "
    break
  fi
  if [ "$i" -eq "$KEYCLOAK_TIMEOUT" ]; then
    echo ""
    echo "❌ Keycloak did not become ready in ${KEYCLOAK_TIMEOUT}s"
    echo "   Check logs: task logs:service -- keycloak"
    exit 1
  fi
  printf "\r   ⏳ %ds / %ds" "$i" "$KEYCLOAK_TIMEOUT"
  sleep 1
done

echo ""
echo "🔐 Initializing Keycloak (realm, clients, test users)..."
bash infra/scripts/setup-keycloak.sh \
  --wait --theme \
  --create-org "ChapsMind Dev" --org-domain chapsmind.local \
  --add-users admin,company_manager,company_viewer,no_access

# ─── 8. Run migrations ───────────────────────────────

echo ""
echo "🗃️  Running database migrations..."
docker compose exec screen alembic upgrade head 2>/dev/null || echo "  ⚠️  Screen service not running — skipping screen migrations"
docker compose exec stream alembic upgrade head 2>/dev/null || echo "  ⚠️  Stream service not running — skipping stream migrations"

# ─── 9. Done ─────────────────────────────────────────

echo ""
echo "════════════════════════════════════════════════════"
echo "  ChapsMind is ready!"
echo "════════════════════════════════════════════════════"
echo ""
echo "  Application → http://localhost         (frontend + API)"
echo "  API Docs    → http://localhost/docs    (Swagger UI)"
echo "  Keycloak    → http://localhost:8080    (admin / admin)"
echo ""
echo ""
echo "  Test users:"
echo "    admin / admin123              (full access)"
echo "    company_manager / manager123  (company.create, organization.read/write)"
echo "    company_viewer / viewer123    (organization.read)"
echo "    no_access / noaccess123       (no permissions)"
echo ""
echo "  Daily usage:"
echo "    task up              Start all services"
echo "    task down            Stop all services"
echo "    task restart         Restart all services"
echo "    task logs            Tail all logs"
echo "    task logs:service -- screen   Tail a specific service"
echo ""
echo "  Development:"
echo "    task screen:shell    Open shell in screen container"
echo "    task screen:test     Run screen tests"
echo "    task screen:lint     Lint screen code"
echo "    task stream:test     Run stream tests"
echo "    task stream:lint     Lint stream code"
echo "    task front:lint      Lint frontend code"
echo "    task front:typecheck TypeScript type checking"
echo ""
echo "  Database:"
echo "    task migrate         Run Alembic migrations"
echo "    task db:shell        Open psql shell"
echo ""
echo "  Run 'task' to see all available commands."
echo ""
