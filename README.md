# ChapsMind Workspace

Workspace repository containing all ChapsMind projects as git submodules.

## Structure

```
chapsmind-workspace/
├── front/      # Vue 3 Frontend (submodule)
├── back/       # FastAPI Backend (submodule)
├── infra/      # Docker & Infrastructure (submodule)
├── .claude/    # Claude AI configuration
├── agent-os/   # Agent OS specs & standards
└── CLAUDE.md   # Project documentation
```

## Quick Start

### 1. Clone the Workspace

```bash
# Clone with all submodules in one command
git clone --recursive ssh://git@git.mediaspeech.com:17890/mint/chapsmind-workspace.git

# Navigate to workspace
cd chapsmind-workspace
```

**Alternative**: If you already cloned without `--recursive`:

```bash
git submodule update --init --recursive
```

### 2. Login to Docker Registry

```bash
docker login registry.git.mediaspeech.com
```

### 3. Start Development Environment

```bash
cd infra

# Build and start all services
docker compose up -d --build

# Check services are running
docker compose ps
```

This will start:
- **db**: PostgreSQL database (port 5432)
- **rabbitmq**: Message broker (ports 5672, 15672)
- **backend**: FastAPI API (port 8000)
- **backend_celery_worker**: Background task processor
- **backend_celery_flower**: Celery monitoring (port 5555)
- **frontend**: Vue.js app (port 3000)

> **Note**: Keycloak runs locally at http://localhost:8080. Admin console: admin/admin

### 4. Initialize Keycloak

After Keycloak starts, run the initialization script to configure service accounts and create a default organization:

```bash
cd infra
./scripts/init-keycloak.sh
```

### 5. Run Database Migrations

```bash
docker compose exec backend alembic upgrade head
```

### 6. (Optional) Seed Sample Data

```bash
docker compose exec backend python scripts/seed_companies.py
```

## Service URLs

| Service          | URL                          | Description               |
|------------------|------------------------------|---------------------------|
| Frontend         | http://localhost:3000        | Vue.js application        |
| Backend API      | http://localhost:8000/api    | FastAPI endpoints         |
| API Docs         | http://localhost:8000/docs   | Swagger documentation     |
| Celery Flower    | http://localhost:5555        | Task monitoring           |
| RabbitMQ Admin   | http://localhost:15672       | Message broker (guest/guest) |
| Keycloak         | http://localhost:8080        | Authentication (local)        |
| Keycloak Admin   | http://localhost:8080/admin  | Admin console (admin/admin)   |

## Working with Submodules

### Update All Submodules

```bash
git submodule update --remote --merge
```

### Pull Latest Changes (All Repos)

```bash
# Pull workspace changes
git pull

# Update submodules to tracked commits
git submodule update --init --recursive
```

### Commit Changes in a Submodule

```bash
# Work in a submodule (e.g., front/)
cd front
git checkout main
git pull
# Make changes...
git add .
git commit -m "Your commit message"
git push

# Then update workspace to reference new commit
cd ..
git add front
git commit -m "Update front submodule"
git push
```

## Common Commands

### Docker (from ./infra/)

```bash
# Start services
docker compose up -d

# Build and start (after code changes)
docker compose up -d --build

# View logs
docker compose logs -f backend

# Restart a service
docker compose restart backend

# Stop all services
docker compose down

# Stop and remove volumes (clean reset)
docker compose down -v

# Enter backend shell
docker compose exec backend bash
```

### Database Migrations (from ./infra/)

```bash
# Run migrations
docker compose exec backend alembic upgrade head

# Create new migration
docker compose exec backend alembic revision -m "description"

# Check current version
docker compose exec backend alembic current

# Downgrade one version
docker compose exec backend alembic downgrade -1
```

### Frontend Development

For hot-reload during frontend development, run the dev server locally instead of using Docker:

```bash
cd front
pnpm install
pnpm run dev
```

Then access at http://localhost:5173 (Vite dev server port).

## Authentication

This setup uses **local Keycloak** at `http://localhost:8080`.

### Test Users

| Username         | Password        | Permissions                           |
|------------------|-----------------|---------------------------------------|
| admin            | admin123        | Full admin access                     |
| company_manager  | manager123      | Company management (view/create/delete) |
| company_viewer   | viewer123       | Company view only                     |
| team_manager     | teammanager123  | Team management                       |
| no_access        | noaccess123     | No permissions (for testing 403)      |

## Troubleshooting

### Submodule Issues

```bash
# If submodules are empty
git submodule update --init --recursive

# If submodules are outdated
git submodule update --remote --merge

# Reset submodule to tracked commit
git submodule update --force
```

### Docker Issues

```bash
# Rebuild containers from scratch
docker compose build --no-cache

# Remove all containers and volumes (full reset)
docker compose down -v

# Check container logs
docker compose logs backend

# Check if ports are in use
lsof -i :3000
lsof -i :8000
```

### Database Issues

```bash
# Reset database (WARNING: deletes all data)
docker compose down -v
docker compose up -d db
docker compose exec backend alembic upgrade head

# Connect to database directly
docker compose exec db psql -U postgres -d mint_db
```

## Claude Code Integration

This workspace is configured for Claude Code with:
- **CLAUDE.md**: Complete project documentation and guidelines
- **.claude/**: Agents, commands, and skills configuration
- **agent-os/**: Product specs, roadmap, and coding standards

To use Claude Code:

```bash
cd chapsmind-workspace
claude
```
