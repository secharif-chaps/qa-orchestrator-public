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

# ─── 4. Install frontend dependencies ────────────────

echo ""
echo "📦 Installing frontend dependencies..."
docker run --rm -w /app \
  -v "$(pwd)/apps/front:/app" \
  node:24 \
  sh -c "corepack enable && yarn install"

# ─── 5. Build and start services ─────────────────────

echo ""
echo "🐳 Building and starting services..."
docker compose build screen
docker compose up -d --build

# ─── 6. Wait for Keycloak + init ─────────────────────

echo ""
echo "⏳ Waiting for Keycloak to be ready..."
for i in $(seq 1 60); do
  if curl -sf http://localhost:8080/realms/master > /dev/null 2>&1; then
    echo "✅ Keycloak is ready"
    break
  fi
  if [ "$i" -eq 60 ]; then
    echo "❌ Keycloak did not become ready in time"
    echo "   Check logs: task logs:service -- keycloak"
    exit 1
  fi
  sleep 3
done

echo ""
echo "🔐 Initializing Keycloak (realm, clients, test users)..."
bash infra/scripts/init-keycloak.sh

# ─── 7. Run migrations ───────────────────────────────

echo ""
echo "🗃️  Running database migrations..."
docker compose exec screen alembic upgrade head

# ─── 8. Done ─────────────────────────────────────────

echo ""
echo "════════════════════════════════════════════════════"
echo "  ChapsMind is ready!"
echo "════════════════════════════════════════════════════"
echo ""
echo "  Application → http://localhost         (frontend + API)"
echo "  API Docs    → http://localhost/docs     (Swagger UI)"
echo "  Keycloak    → http://localhost:8080    (admin / admin)"
echo "  RabbitMQ    → http://localhost:15672   (guest / guest)"
echo ""
echo "  Test users:"
echo "    admin / admin123              (full access)"
echo "    company_manager / manager123  (company.create, organization.read/write)"
echo "    company_viewer / viewer123    (organization.read)"
echo "    no_access / noaccess123       (no permissions)"
echo ""
echo "  Run 'task' to see all available commands."
echo ""