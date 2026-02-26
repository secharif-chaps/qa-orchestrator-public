# ChapsMind

A modern platform for monitoring companies online. Built as a monorepo with a Vue 3 frontend, FastAPI backend, and infrastructure-as-code.

## Project Structure

```
chapsmind/
├── apps/
│   ├── front/              # Vue 3 frontend (TypeScript, Tailwind CSS v4)
│   ├── screen/             # FastAPI backend (Python, SQLAlchemy, Celery)
│   └── global-service/     # Global service
├── infra/                  # Docker Compose, CI/CD configuration
├── docs/                   # Documentation (ADRs, architecture, product, specs, standards)
├── agent-os/               # Agent OS configuration (product docs, specs, standards)
├── scripts/                # CI scripts, subtree sync
├── .claude/                # Claude AI agents, commands, skills
├── .gitlab/                # CODEOWNERS
├── Taskfile.yml            # Task runner
├── CLAUDE.md               # AI assistant instructions
└── README.md               # This file
```

## Prerequisites

- [Docker](https://docs.docker.com/get-docker/) and Docker Compose
- [Task](https://taskfile.dev/installation/) (task runner)
- [Node.js](https://nodejs.org/) (LTS) with [Corepack](https://nodejs.org/api/corepack.html) enabled (for Yarn 4)

```bash
# Enable corepack to use Yarn 4
corepack enable
```

## Quick Start

### 1. Clone the Repository

```bash
git clone ssh://git@git.mediaspeech.com:17890/chapsmind/chapsmind.git
cd chapsmind
```

### 2. Initialize the Project

```bash
task init
```

This single command will:
- Copy `.env.example` to `.env` (if not already present)
- Install frontend dependencies (`yarn install`)
- Build and start all Docker services
- Run database migrations

### 3. Access the Application

Open [http://localhost:3000](http://localhost:3000) in your browser.

## Available Commands

All commands use [Task](https://taskfile.dev/). Run `task` (with no arguments) to list all available commands.

### Infrastructure

| Command            | Description                          |
|--------------------|--------------------------------------|
| `task up`          | Start all services (build + detach)  |
| `task down`        | Stop all services                    |
| `task restart`     | Restart all services                 |
| `task logs`        | Tail all service logs                |
| `task logs:service -- screen` | Tail logs for a specific service |

### Database

| Command              | Description                    |
|----------------------|--------------------------------|
| `task migrate`       | Run Alembic migrations         |
| `task migrate:status`| Show current migration version |
| `task seed`          | Seed sample data               |
| `task db:shell`      | Open psql shell                |

### Frontend

| Command              | Description                    |
|----------------------|--------------------------------|
| `task front:dev`     | Start dev server with HMR      |
| `task front:lint`    | Lint and fix frontend code     |
| `task front:typecheck` | Run TypeScript type checking |
| `task front:build`   | Build for production           |

### Screen (Backend)

| Command              | Description                    |
|----------------------|--------------------------------|
| `task screen:lint`   | Lint backend code (ruff)       |
| `task screen:format` | Format backend code (ruff)     |
| `task screen:test`   | Run backend tests              |
| `task screen:shell`  | Open bash shell in container   |

### All Projects

| Command     | Description         |
|-------------|---------------------|
| `task lint`  | Lint all projects   |
| `task test`  | Run all tests       |

## Services

| Service          | URL                          | Description                   |
|------------------|------------------------------|-------------------------------|
| Frontend         | http://localhost:3000         | Vue.js application            |
| Backend API      | http://localhost:8000/api     | FastAPI endpoints             |
| API Docs         | http://localhost:8000/docs    | Swagger documentation         |
| Celery Flower    | http://localhost:5555         | Task monitoring               |
| RabbitMQ Admin   | http://localhost:15672        | Message broker (guest/guest)  |

## Authentication

Authentication is handled via **Keycloak** (integration server) at `https://sso.dwcode.team/auth`.

### Test Users

| Username         | Password        | Permissions                             |
|------------------|-----------------|-----------------------------------------|
| admin            | admin123        | Full admin access                       |
| company_manager  | manager123      | Company management (view/create/delete) |
| company_viewer   | viewer123       | Company view only                       |
| team_viewer      | teamviewer123   | Team read-only + company view           |
| team_manager     | teammanager123  | Team management + company view          |
| no_access        | noaccess123     | No permissions (for testing 403)        |

## Git Workflow

### Branch Naming

```
feat/TAR-xxx-feature-name
fix/TAR-xxx-bug-name
refactor/TAR-xxx-description
docs/TAR-xxx-description
chore/TAR-xxx-description
```

### Commit Format

Uses gitmoji + conventional commits:

```
<gitmoji> <type>(<scope>): TAR-xxx <description>
```

Examples:
```
feat(front): TAR-42 add company search filters
fix(screen): TAR-15 resolve pagination offset error
docs: TAR-99 update API endpoint documentation
```

### Workflow

1. Create a feature branch from `main`: `git checkout -b feat/TAR-xxx-feature-name`
2. Make changes and commit using the format above
3. Push: `git push -u origin feat/TAR-xxx-feature-name`
4. Create a Merge Request for code review
5. After approval, merge to `main`

**Rules**:
- Never commit directly to `main`
- Always create a Merge Request before merging
- Never force push to `main`

## Troubleshooting

### Docker Issues

```bash
# Rebuild containers from scratch
task down
task up

# Full reset (removes volumes and data)
docker compose -f infra/docker-compose.yml -f infra/docker-compose.local.yml down -v
task up
task migrate
```

### Port Conflicts

```bash
lsof -i :3000
lsof -i :8000
```

## Claude Code Integration

This repository is configured for [Claude Code](https://claude.ai/code):

- **CLAUDE.md**: Complete project documentation and AI assistant guidelines
- **.claude/**: Specialized agents, commands, and skills
- **agent-os/**: Product specs, roadmap, and coding standards

```bash
cd chapsmind
claude
```
