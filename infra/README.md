# ChapsMind Local Development Environment

Complete local development setup with Keycloak, PostgreSQL, RabbitMQ, and backend services.

## Prerequisites

- Docker and Docker Compose
- [Task](https://taskfile.dev/) (optional but recommended)

## File Structure

- `compose.yaml` - Base configuration (preprod/production)
- `compose.local.yaml` - Local development overrides (builds from `../apps/`)

## Fresh Installation

For new developers setting up the project for the first time:

```bash
# From the monorepo root, the recommended way:
task init

# Or manually from the infra directory:
cd infra
docker compose -f compose.yaml -f compose.local.yaml up -d --build
./scripts/init-keycloak.sh
docker compose -f compose.yaml -f compose.local.yaml exec screen alembic upgrade head
docker compose -f compose.yaml -f compose.local.yaml restart screen

# (Optional) Seed sample data for testing
./scripts/seed-workflow-configs.sh
./scripts/seed-sample-data.sh
```

**Tip**: Use `task` from the monorepo root instead of raw docker compose commands.

That's it! The local environment is now ready.

## Services

| Service | URL | Description |
|---------|-----|-------------|
| Backend API | http://localhost:8000/api | FastAPI backend |
| Keycloak | http://localhost:8080 | Identity provider |
| Keycloak Admin | http://localhost:8080/admin | Admin console (admin/admin) |
| PostgreSQL | localhost:5432 | Database |
| RabbitMQ | localhost:5672 | Message broker |
| RabbitMQ Admin | http://localhost:15672 | Management (guest/guest) |
| Flower | http://localhost:5555 | Celery monitoring |

## Test Users

All test users are assigned to the "ChapsMind Dev" organization:

| Username | Password | Roles |
|----------|----------|-------|
| admin | admin123 | Full admin access |
| company_manager | manager123 | company.view, company.create, company.delete |
| company_viewer | viewer123 | company.view (read-only) |
| team_manager | teammanager123 | company.view, organization.read, organization.write |
| no_access | noaccess123 | No permissions |

## Frontend Development

The frontend is **not** started by default. Run it separately:

```bash
# From monorepo root:
task front:dev

# Or manually:
cd apps/front
pnpm install
pnpm dev
```

Frontend runs at http://localhost:5173

## Common Commands

From monorepo root using `task`, or with `dc` alias:

```bash
# View logs
task logs                     # or: dc logs -f screen
task logs:service -- screen  # specific service

# Restart a service
dc restart screen

# Run migrations
task migrate                  # or: dc exec screen alembic upgrade head

# Enter backend shell
task screen:shell             # or: dc exec screen bash

# Stop all services
task down                     # or: dc down

# Full reset (removes all data)
dc down -v
```

## Complete Reset

To wipe everything and start fresh (like a new developer):

```bash
# Stop and remove all containers and volumes
dc down -v

# Start fresh
dc up -d --build

# Re-run initialization
./scripts/init-keycloak.sh
dc exec screen alembic upgrade head
dc restart screen

# (Optional) Seed sample data
./scripts/seed-workflow-configs.sh
./scripts/seed-sample-data.sh
```

## Seed Scripts

Optional scripts to populate your local database with sample data for development.

### Workflow Configs

Seeds the `workflow_configs` table with Dify API keys:

```bash
./scripts/seed-workflow-configs.sh
```

This populates all 9 task types with their API keys and LLM settings.

### Sample Data

Creates sample folder and companies for testing:

```bash
./scripts/seed-sample-data.sh
```

This creates:
- 1 folder: "Sample Companies"
- 3 companies: EDF, ChapsVision, Palantir (with full profile data)
- Links companies to the folder

Both scripts are **idempotent** - safe to run multiple times without creating duplicates.

## Keycloak Configuration

The local Keycloak is pre-configured with:

- **Realm**: `chapsmind`
- **Clients**:
  - `chapsmind-front` - Public client for Vue.js frontend
  - `chapsmind-screen-back` - Confidential client for FastAPI backend
  - `chapsmind-admin` - Service account for admin operations
- **Organizations**: Enabled with one default organization
- **Roles**: company.view, company.create, company.update, company.delete, organization.read, organization.write, admin.organizations, admin.tasks, admin.workflows

## Dify Integration

The backend connects to a remote Dify instance for AI workflows. To test workflows locally, the Dify server needs to call back to your local backend.

### Dify Workflow API Keys (Production)

| Task Type | API Key | LLM |
|-----------|---------|-----|
| data_collection | `app-qSdKHTLoR0WiESMRcVBKSzlI` | mistral |
| csr | `app-k8W9ZQkZUIcsvBo5ZWgY3RLE` | mistral |
| digital | `app-sU5GPQX6og1nwtckA57iCDeL` | mistral |
| jobs | `app-nZiwseUyw8f6H208ohPx0o1K` | mistral |
| press | `app-ET1gBTLFPsVD8qlYSL46O3Hd` | mistral |
| products | `app-WpGZCTFDaBzCUS9M4LeoQHGa` | mistral |
| profile | `app-4K16XfNZP4ZhUutfoLEZYiKy` | mistral |
| team | `app-LTspKNxtk6nTJOH4YkBdyU2Q` | mistral |
| timeline | `app-qX4RISdrrif2aSPAaLVz7tto` | mistral |

These are stored in the `workflow_configs` table in the database.

### Localtunnel Setup for Dify Callbacks

Since Dify runs on a remote server, it needs a public URL to call back to your local backend. A **localtunnel** service is included in the Docker Compose setup for this purpose.

#### How it works

The tunnel service exposes your local backend (port 8000) via a public URL (e.g. `https://xxxxx.loca.lt`). The backend and Celery worker automatically read this URL from a shared volume, so Dify can send task results back to your local machine.

#### Start the tunnel (on-demand)

The tunnel does **not** start automatically with `task up`. Start it explicitly when you need Dify callbacks:

```bash
docker compose -f infra/compose.yaml -f infra/compose.local.yaml --profile tunnel up -d tunnel
```

#### Stop the tunnel

```bash
docker compose -f infra/compose.yaml -f infra/compose.local.yaml --profile tunnel stop tunnel
```

#### Optional: consistent subdomain

Set `TUNNEL_SUBDOMAIN` in your `.env` to get a consistent URL across restarts:

```bash
TUNNEL_SUBDOMAIN=my-chapsmind-dev
```

This gives you `https://my-chapsmind-dev.loca.lt` every time.

#### How callbacks work

1. The tunnel container writes its public URL to a shared Docker volume (`tunnel_url`)
2. The backend reads this URL from `TUNNEL_URL_FILE` at `/tunnel/url`
3. In dev mode, the frontend fetches the tunnel URL via `GET /api/dev/tunnel-url`
4. When creating a company, the frontend passes the tunnel URL as `callback_base_url`
5. Dify uses this URL to POST task results back to your local backend

## Troubleshooting

### Backend fails to start
If the backend shows Keycloak connection errors, ensure Keycloak is fully started and run:
```bash
./scripts/init-keycloak.sh
dc restart screen
```

### Token missing organization ID
The init script configures the organization mapper. Re-run if needed:
```bash
./scripts/init-keycloak.sh
```

### Database connection issues
```bash
dc logs db
dc restart screen
```

### Full environment reset
```bash
dc down -v
dc up -d --build
./scripts/init-keycloak.sh
dc exec screen alembic upgrade head
```

### Dify callbacks not working
1. Ensure the tunnel is running: `docker compose -f infra/compose.yaml -f infra/compose.local.yaml --profile tunnel up -d tunnel`
2. Check the tunnel URL was generated: `docker compose -f infra/compose.yaml -f infra/compose.local.yaml exec tunnel cat /tunnel/url`
3. Restart backend so it picks up the URL: `docker compose -f infra/compose.yaml -f infra/compose.local.yaml restart screen`
4. Check tunnel logs: `docker compose -f infra/compose.yaml -f infra/compose.local.yaml logs tunnel`
