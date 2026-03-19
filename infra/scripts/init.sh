#!/bin/bash
# One-shot first-time setup for ChapsMind local development.
# Usage: bash infra/scripts/init.sh
#
# What it does:
#   1. Create .env from template
#   2. Auto-generate secrets (ENCRYPTION_KEY, INTERNAL_JWT_SECRET, TUNNEL_SUBDOMAIN)
#   3. Configure frontend private registry (.yarnrc.yml)
#   4. Install frontend dependencies
#   5. Build and start all Docker services
#   6. Wait for Keycloak + init realm/clients/users
#   7. Run database migrations
#   8. Print URLs and test users

set -euo pipefail

# ─── 1. Create .env ──────────────────────────────────

if [ ! -f .env ]; then
  cp .env.example .env
  echo "✅ Created .env from .env.example"
else
  echo "ℹ️  .env already exists, skipping copy"
fi

# Load COMPOSE_FILE and other vars from .env
set -a
source .env
set +a

# ─── 2. Auto-generate secrets ────────────────────────

if grep -q '^ENCRYPTION_KEY=changeme$' .env; then
  KEY=$(python3 -c "import secrets; print(secrets.token_hex(32))")
  sed -i "s/^ENCRYPTION_KEY=changeme$/ENCRYPTION_KEY=${KEY}/" .env
  echo "✅ Auto-generated ENCRYPTION_KEY"
fi

if grep -q '^INTERNAL_JWT_SECRET=changeme$' .env; then
  SECRET=$(openssl rand -base64 32)
  sed -i "s|^INTERNAL_JWT_SECRET=changeme$|INTERNAL_JWT_SECRET=${SECRET}|" .env
  echo "✅ Auto-generated INTERNAL_JWT_SECRET"
fi

if grep -q '^TUNNEL_SUBDOMAIN=chapsmind-dev-changeme$' .env; then
  RANDOM_ID=$(python3 -c "import secrets; print(secrets.token_hex(4))")
  sed -i "s/^TUNNEL_SUBDOMAIN=chapsmind-dev-changeme$/TUNNEL_SUBDOMAIN=chapsmind-dev-${RANDOM_ID}/" .env
  echo "✅ Auto-generated TUNNEL_SUBDOMAIN=chapsmind-dev-${RANDOM_ID}"
fi

# ─── 3. Configure .yarnrc.yml ────────────────────────

bash infra/scripts/setup-yarnrc.sh

# ─── 4. Install dependencies ──────────────────────────

echo ""
echo "📦 Installing dev tools (husky, lint-staged, commitlint)..."
docker run --rm -w /app \
  -v "$(pwd):/app" \
  node:24 \
  sh -c "git config --global --add safe.directory /app && corepack enable && yarn install"

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
  docker compose down
fi

mapfile -t REQUIRED_PORTS < <(docker compose config 2>/dev/null | grep -oP 'published: "\K\d+' | sort -u)
BUSY_PORTS=()
for port in "${REQUIRED_PORTS[@]}"; do
  if ss -tlnH "sport = :$port" 2>/dev/null | grep -q .; then
    PROCESS=$(ss -tlnpH "sport = :$port" 2>/dev/null | head -1 | grep -oP 'users:\(\("\K[^"]+' || echo "unknown")
    BUSY_PORTS+=("$port ($PROCESS)")
  fi
done

if [ ${#BUSY_PORTS[@]} -gt 0 ]; then
  echo "❌ Required ports are already in use:"
  for p in "${BUSY_PORTS[@]}"; do echo "   → port $p"; done
  echo ""
  echo "   Free these ports and re-run 'task init'."
  exit 1
fi

# ─── 6. Build and start services ─────────────────────

echo ""
echo "🐳 Building and starting services..."
docker compose build screen
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
bash infra/scripts/init-keycloak.sh

# ─── 8. Run migrations ───────────────────────────────

echo ""
echo "🗃️  Running database migrations..."
docker compose exec screen alembic upgrade head

# ─── 9. Done ─────────────────────────────────────────

echo ""
echo "════════════════════════════════════════════════════"
echo "  ChapsMind is ready!"
echo "════════════════════════════════════════════════════"
echo ""
echo "  Application → http://localhost         (frontend + API)"
echo "  API Docs    → http://localhost/docs    (Swagger UI)"
echo "  Keycloak    → http://localhost:8080    (admin / admin)"
echo "  RabbitMQ    → http://localhost:15672   (guest / guest)"
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
echo "    task screen:shell    Open shell in backend container"
echo "    task screen:test     Run backend tests"
echo "    task screen:lint     Lint backend code"
echo "    task front:lint      Lint frontend code"
echo "    task front:typecheck TypeScript type checking"
echo ""
echo "  Database:"
echo "    task migrate         Run Alembic migrations"
echo "    task db:shell        Open psql shell"
echo ""
echo "  Run 'task' to see all available commands."
echo ""
