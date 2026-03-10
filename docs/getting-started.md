# Getting Started

> **Last Updated:** 2026-03-07

Complete step-by-step guide to set up ChapsMind on your local machine. Supports **Ubuntu**, **Windows (WSL2)**, and **macOS**.

---

## Table of Contents

- [Prerequisites](#prerequisites)
  - [1. Install WSL (Windows only)](#1-install-wsl-windows-only)
  - [2. Install Docker](#2-install-docker)
  - [3. Install jq](#3-install-jq)
  - [4. Install Node.js](#4-install-nodejs)
  - [5. Enable Corepack (Yarn 4)](#5-enable-corepack-yarn-4)
  - [6. Install Task](#6-install-task)
  - [7. Configure SSH Access](#7-configure-ssh-access)
- [Project Setup](#project-setup)
  - [1. Clone the Repository](#1-clone-the-repository)
  - [2. Configure the Private Registry (Vuellar)](#2-configure-the-private-registry-vuellar)
  - [3. Environment Variables](#3-environment-variables)
  - [4. Initialize the Project](#4-initialize-the-project)
  - [5. Initialize Keycloak](#5-initialize-keycloak)
  - [6. Run Database Migrations](#6-run-database-migrations)
  - [7. Verify Setup](#7-verify-setup)
- [Running the Application](#running-the-application)
- [Available Commands](#available-commands)
- [VS Code Tasks (Terminal)](#vs-code-tasks-terminal)
- [Services & URLs](#services--urls)
- [Test Users](#test-users)
- [Troubleshooting](#troubleshooting)
- [Related Documentation](#related-documentation)

---

## Prerequisites

> **macOS users:** Most tools below are installed via [Homebrew](https://brew.sh/). If you don't have it yet, install it first:
> ```bash
> /bin/bash -c "$(curl -fsSL https://raw.githubusercontent.com/Homebrew/install/HEAD/install.sh)"
> ```

### 1. Install WSL (Windows only)

If you are on Windows, install WSL 2 with an Ubuntu distribution:

```powershell
# Run in PowerShell as Administrator
wsl --install -d Ubuntu
```

Restart your machine, then open the Ubuntu terminal to complete setup. All subsequent commands should be run **inside WSL**.

> **Tip:** Make sure WSL 2 is the default version: `wsl --set-default-version 2`

### 2. Install Docker

Docker is required to run all backend services (database, RabbitMQ, API, Celery).

#### Ubuntu / WSL

```bash
# Update packages
sudo apt update && sudo apt upgrade -y

# Install Docker
sudo apt install -y ca-certificates curl gnupg
sudo install -m 0755 -d /etc/apt/keyrings
curl -fsSL https://download.docker.com/linux/ubuntu/gpg | sudo gpg --dearmor -o /etc/apt/keyrings/docker.gpg
sudo chmod a+r /etc/apt/keyrings/docker.gpg

echo \
  "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.gpg] https://download.docker.com/linux/ubuntu \
  $(. /etc/os-release && echo "$VERSION_CODENAME") stable" | \
  sudo tee /etc/apt/sources.list.d/docker.list > /dev/null

sudo apt update
sudo apt install -y docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin

# Allow running Docker without sudo
sudo usermod -aG docker $USER
newgrp docker

# Verify installation
docker --version
docker compose version
```

> **Windows users:** Alternatively, install [Docker Desktop](https://www.docker.com/products/docker-desktop/) with WSL 2 backend enabled — this is the recommended approach.

#### macOS

Install [Docker Desktop for Mac](https://www.docker.com/products/docker-desktop/):

```bash
# Option 1: Install via Homebrew (recommended)
brew install --cask docker

# Then launch Docker Desktop from Applications and wait for the engine to start

# Option 2: Download the .dmg directly from https://www.docker.com/products/docker-desktop/

# Verify installation
docker --version
docker compose version
```

> **Tip:** Docker Desktop for Mac includes Docker Compose v2 out of the box. No additional install needed.

### 3. Install jq

`jq` is a lightweight command-line JSON processor used by project scripts:

```bash
# Ubuntu / WSL
sudo apt-get install -y jq

# macOS
brew install jq

# Verify
jq --version
```

### 4. Install Node.js

Install Node.js **v22** (LTS) using [nvm](https://github.com/nvm-sh/nvm) (recommended):

> **Note:** Vite requires Node.js 20.19+ or 22.12+. Node 21.x is **not** supported.

```bash
# Install nvm
curl -o- https://raw.githubusercontent.com/nvm-sh/nvm/v0.40.1/install.sh | bash

# Reload shell
source ~/.bashrc   # Ubuntu / WSL
source ~/.zshrc    # macOS (zsh is the default shell)

# Install and use the required Node.js version
nvm install 22
nvm use 22
nvm alias default 22

# Verify
node --version   # v22.x.x
```

### 5. Enable Corepack (Yarn 4)

Corepack manages the correct Yarn version (4.9.1) automatically:

```bash
# Enable Corepack
corepack enable

# Verify Yarn is available (version will be set per-project)
yarn --version   # 4.9.1
```

### 6. Install Task

[Task](https://taskfile.dev/) is the command runner used across the monorepo:

```bash
# Ubuntu / WSL
sh -c "$(curl --location https://taskfile.dev/install.sh)" -- -d -b /usr/local/bin

# macOS (via Homebrew)
brew install go-task

# Verify
task --version
```

### 7. Configure SSH Access

You need SSH access to `git.mediaspeech.com` to clone the repository and sync with legacy repos.

```bash
# Generate an SSH key (if you don't have one)
ssh-keygen -t ed25519 -C "your.email@example.com"

# Start the SSH agent
eval "$(ssh-agent -s)"
ssh-add ~/.ssh/id_ed25519

# Display your public key — add it to your GitLab account
cat ~/.ssh/id_ed25519.pub
```

Add the public key in **GitLab > Preferences > SSH Keys**.

Test the connection:

```bash
ssh -T -p 17890 git@git.mediaspeech.com
```

---

## Project Setup

### 1. Clone the Repository

```bash
git clone ssh://git@git.mediaspeech.com:17890/chapsmind/chapsmind.git
cd chapsmind
```

### 2. Configure the Private Registry (Vuellar)

The frontend depends on `@owlint/feathers-vue` (Vuellar), hosted on a private npm registry. You need credentials from **Passbolt**.

**Step 1:** Open Passbolt and retrieve the credentials:

> https://passbolt.localnet/app/passwords/view/4b9713f0-e896-4bc3-b5c5-4d81403a73fb

Look for the entry named **CHAPSMIND_VUELLAR**. You will need:
- `OWLINT_REGISTRY_URL` — the private registry URL
- `OWLINT_DEPLOY_KEY` — the authentication token

**Step 2:** Add the credentials to your `.env` file:

```bash
# Copy .env.example first if you haven't
cp .env.example .env

# Edit .env and set the registry credentials
# OWLINT_REGISTRY_URL=<url-from-passbolt>
# OWLINT_DEPLOY_KEY=<key-from-passbolt>
```

> **Important:** These environment variables are required for Docker builds. Without them, `task up` will fail.

**Step 3:** Configure the local frontend `.yarnrc.yml`:

```bash
task front:setup-yarnrc
```

This generates `apps/front/.yarnrc.yml` from the template using the credentials from your environment or prompting you interactively.

### 3. Environment Variables

Copy the example environment file and adjust values as needed:

```bash
cp .env.example .env
```

Key variables to review:

| Variable | Default | Notes |
|----------|---------|-------|
| `KEYCLOAK_ADMIN_CLIENT_SECRET` | `changeme` | Ask a team member |
| `DIFY_API_KEY` | `changeme` | Ask a team member |
| `ENCRYPTION_KEY` | `changeme` | Generate with: `python3 -c "import secrets; print(secrets.token_hex(32))"` |

> Most defaults work out of the box for local development. Only the values marked `changeme` need attention.

### 4. Initialize the Project

Run the one-command setup:

```bash
task init
```

This will:

1. Copy `.env.example` to `.env` (if not already done)
2. Configure `apps/front/.yarnrc.yml` (prompts for registry credentials if needed)
3. Install frontend dependencies (`yarn install`)
4. Build and start all Docker services
5. Display available commands

### 5. Initialize Keycloak

After Docker services are running, initialize Keycloak with the realm, clients, and test users:

```bash
cd infra && ./scripts/init-keycloak.sh && cd ..
```

This script:
- Creates the `chapsmind` realm
- Configures OAuth clients for frontend and backend
- Creates test users with different permission levels

### 6. Run Database Migrations

Apply database migrations:

```bash
task migrate
```

### 7. Verify Setup

Check all services are running correctly:

```bash
# Check container status
docker compose -f infra/compose.yaml -f infra/compose.local.yaml ps

# All containers should show "healthy" or "running"
```

Test the services:

| Service | URL | Expected |
|---------|-----|----------|
| Frontend | http://localhost:3000 | Login page |
| API Docs | http://localhost:8000/docs | Swagger UI |
| Keycloak | http://localhost:8080 | Keycloak admin console |
| RabbitMQ | http://localhost:15672 | RabbitMQ dashboard |

---

## Running the Application

### First-Time Setup (Full)

For a complete first-time setup with Keycloak initialization:

```bash
# Start all services
task up

# Wait for services to be healthy (check status)
docker compose -f infra/compose.yaml -f infra/compose.local.yaml ps

# Initialize Keycloak (creates realm, clients, test users)
cd infra && ./scripts/init-keycloak.sh && cd ..

# Run database migrations
task migrate

# (Optional) Seed sample data
task seed
```

### Daily Usage

```bash
# Start all services
task up

# Open the app
# Frontend: http://localhost:3000
# API Docs: http://localhost:8000/docs
# Keycloak: http://localhost:8080
```

### Stopping Services

```bash
task down
```

### Restarting Services

```bash
task restart
```

---

## Available Commands

Run `task` with no arguments to see all available commands. Here are the most common ones:

### Infrastructure

| Command | Description |
|---------|-------------|
| `task up` | Start all services (build + detach) |
| `task down` | Stop all services |
| `task restart` | Restart all services |
| `task logs` | Tail all service logs |
| `task logs:service -- screen` | Tail logs for a specific service |

### Database

| Command | Description |
|---------|-------------|
| `task migrate` | Run Alembic migrations |
| `task migrate:status` | Show current migration version |
| `task seed` | Seed sample data |
| `task db:shell` | Open psql shell |

### Frontend

| Command | Description |
|---------|-------------|
| `task front:dev` | Start dev server with HMR |
| `task front:lint` | Lint and fix frontend code |
| `task front:typecheck` | Run TypeScript type checking |
| `task front:build` | Build for production |

### Backend (Screen)

| Command | Description |
|---------|-------------|
| `task screen:lint` | Lint backend code (ruff) |
| `task screen:format` | Format backend code (ruff) |
| `task screen:test` | Run backend tests |
| `task screen:shell` | Open bash shell in container |

### Cross-Project

| Command | Description |
|---------|-------------|
| `task lint` | Lint all projects |
| `task test` | Run all tests |

---

## VS Code Tasks (Terminal)

The project includes pre-configured VS Code tasks that you can run directly from the **Terminal** menu (`Terminal > Run Task...`) or with `Ctrl+Shift+P` > "Tasks: Run Task". These provide a quick GUI-driven way to manage the stack without memorizing commands.

### Docker Services

| Task | Description | Command |
|------|-------------|---------|
| **Docker: Full Setup** | Build all services, init Keycloak, run migrations, restart | `docker compose up -d --build && init-keycloak.sh && alembic upgrade head` |
| **Docker: Start All Services** | Start all containers (build + detach) | `docker compose up -d --build` |
| **Docker: Stop All Services** | Stop all containers | `docker compose down` |
| **Docker: Status** | Show running container status | `docker compose ps` |
| **Docker: Restart Service** | Restart a specific service (pick from list) | `docker compose restart <service>` |
| **Docker: Rebuild Service** | Rebuild and restart a specific service | `docker compose up -d --build <service>` |
| **Docker: Full Reset** | Stop all services and **delete all volumes/data** | `docker compose down -v` |

### Logs

| Task | Description |
|------|-------------|
| **Logs: Follow Service** | Tail logs for a specific service (pick from list: screen, global-service, celery, db, rabbitmq, keycloak) |

### Frontend

| Task | Description |
|------|-------------|
| **Front: Dev Server** | Start the frontend dev server with HMR (`yarn dev`) |
| **Front: Run Tests** | Run frontend tests once |
| **Front: Run Tests (watch)** | Run tests in watch mode |
| **Front: Build** | Build for production |
| **Front: Type Check** | Run `vue-tsc --noEmit` |
| **Front: Lint & Format** | Run formatter, stylelint, and eslint |

### Backend (Screen / Global Service)

| Task | Description |
|------|-------------|
| **Run Tests** | Run pytest inside a backend container (pick: screen or global-service) |
| **Run Tests (with coverage)** | Run pytest with coverage report |
| **Shell (bash)** | Open an interactive bash shell in a backend container |

### Database & Migrations

| Task | Description |
|------|-------------|
| **Database: Connect (psql)** | Open a psql shell (pick database: chapsmind_db, global_db, keycloak) |
| **Alembic: Upgrade Head** | Run all pending migrations |
| **Alembic: Current** | Show current migration revision |
| **Alembic: History** | Show migration history |
| **Alembic: New Migration** | Create a new migration (prompts for description) |
| **Alembic: Downgrade -1** | Rollback the last migration |

### Networking

| Task | Description |
|------|-------------|
| **Ngrok: Expose Backend** | Expose the backend API (port 8000) via ngrok for external access |

> **Tip:** The service picker lets you choose between `screen`, `global-service`, `screen_celery_worker`, `screen_celery_flower`, `db`, `rabbitmq`, and `keycloak`.

---

## Services & URLs

Once `task up` is running, these services are available:

| Service | URL | Credentials |
|---------|-----|-------------|
| Frontend | http://localhost:3000 | See [Test Users](#test-users) below |
| Backend API | http://localhost:8000/api | Bearer token from Keycloak |
| API Docs (Swagger) | http://localhost:8000/docs | - |
| Keycloak Admin | http://localhost:8080 | admin / admin |
| Celery Flower | http://localhost:5555 | admin / admin |
| RabbitMQ Admin | http://localhost:15672 | guest / guest |
| Global Service | http://localhost:8001 | Internal service |

> **Note:** Authentication is handled via the local Keycloak instance at `http://localhost:8080`.

---

## Test Users

All test users belong to **Organization 1** (ChapsVision). Use these to test different permission levels:

| Username | Password | Permissions | Description |
|----------|----------|-------------|-------------|
| `admin` | `admin123` | Full access | All features enabled |
| `company_manager` | `manager123` | company.view, company.create, company.delete | Company management |
| `company_viewer` | `viewer123` | company.view | Read-only company access |
| `team_viewer` | `teamviewer123` | organization.read, company.view | Team read-only + company view |
| `team_manager` | `teammanager123` | organization.read, organization.write, company.view | Team management + company view |
| `no_access` | `noaccess123` | None | For testing 403 errors |

---

## Troubleshooting

### Docker won't start

```bash
# Ubuntu / WSL: Start Docker daemon
sudo service docker start

# Check if Docker is running
docker info

# macOS: Open Docker Desktop
open /Applications/Docker.app

# Windows: Ensure Docker Desktop is running with WSL2 backend enabled
# Settings > Resources > WSL Integration > Enable for your distro
```

### Containers fail to build

```bash
# Clean rebuild from scratch
task down
docker system prune -f
task up
```

### Full reset (removes all data)

```bash
docker compose -f infra/compose.yaml -f infra/compose.local.yaml down -v
docker system prune -f
task up
task migrate
```

### Port conflicts

```bash
# Check what's using a port (macOS / Linux)
lsof -i :3000
lsof -i :8000
lsof -i :8080

# Ubuntu / WSL alternative
ss -tlnp | grep -E '3000|8000|8080'

# Kill process using a port (replace PID)
kill -9 <PID>
```

### Yarn install fails (registry auth)

```bash
# Re-configure registry credentials
task front:setup-yarnrc

# Clear Yarn cache and retry
cd apps/front && yarn cache clean && yarn install

# Verify .yarnrc.yml exists and has correct values
cat apps/front/.yarnrc.yml
```

### `task` command not found

```bash
# Ubuntu / WSL: Reinstall Task
sh -c "$(curl --location https://taskfile.dev/install.sh)" -- -d -b /usr/local/bin

# macOS: Reinstall via Homebrew
brew install go-task

# Verify installation
task --version
```

### `jq` command not found

```bash
# Ubuntu / WSL
sudo apt-get update && sudo apt-get install -y jq

# macOS
brew install jq

# Verify installation
jq --version
```

### `corepack` or Yarn issues

```bash
# Enable corepack
corepack enable

# If Yarn version is wrong, force it
corepack prepare yarn@4.9.1 --activate

# Verify
yarn --version  # Should be 4.9.1
```

### SSH connection refused

```bash
# Test SSH access
ssh -T -p 17890 git@git.mediaspeech.com

# If it fails, check your SSH key is added to GitLab and the agent is running
eval "$(ssh-agent -s)"
ssh-add ~/.ssh/id_ed25519

# Verify your key is loaded
ssh-add -l
```

### Keycloak not accessible

```bash
# Check if Keycloak container is running
docker compose -f infra/compose.yaml -f infra/compose.local.yaml ps keycloak

# View Keycloak logs
task logs:service -- keycloak

# Restart Keycloak
docker compose -f infra/compose.yaml -f infra/compose.local.yaml restart keycloak
```

### Database connection issues

```bash
# Check if database is healthy
docker compose -f infra/compose.yaml -f infra/compose.local.yaml ps db

# View database logs
task logs:service -- db

# Connect to database directly
task db:shell
```

### Backend API not responding

```bash
# Check screen container status
docker compose -f infra/compose.yaml -f infra/compose.local.yaml ps screen

# View backend logs
task logs:service -- screen

# Restart backend
docker compose -f infra/compose.yaml -f infra/compose.local.yaml restart screen

# Check if migrations are up to date
task migrate:status
```

### Frontend not loading

```bash
# If using task front:dev (local dev server)
cd apps/front && yarn dev

# Check for port conflicts
lsof -i :3000
lsof -i :5173

# Reinstall dependencies
cd apps/front && rm -rf node_modules && yarn install
```

### WSL-Specific Issues

```bash
# If Docker commands hang, restart WSL
wsl --shutdown
# Then reopen Ubuntu terminal

# If DNS doesn't work in WSL
sudo rm /etc/resolv.conf
sudo bash -c 'echo "nameserver 8.8.8.8" > /etc/resolv.conf'
sudo chattr +i /etc/resolv.conf

# Check WSL version (should be 2)
wsl -l -v
```

### macOS-Specific Issues

```bash
# If Docker Desktop is slow, increase resources
# Docker Desktop > Settings > Resources > Memory (at least 4GB)

# If brew commands fail
brew update && brew upgrade

# Reset Homebrew if corrupted
brew doctor
```

---

## Related Documentation

| Resource | Location | Description |
|----------|----------|-------------|
| Architecture Overview | [`docs/architecture/`](architecture/) | Full technical architecture document |
| Product Mission | [`agent-os/product/mission.md`](../agent-os/product/mission.md) | Product pitch and vision |
| Tech Stack | [`agent-os/product/tech-stack.md`](../agent-os/product/tech-stack.md) | Technology choices and rationale |
| Coding Standards | [`agent-os/standards/`](../agent-os/standards/) | Development conventions |
| AI Assistant Guide | [`CLAUDE.md`](../CLAUDE.md) | Claude Code configuration and project rules |
| Claude Setup Prompts | [`docs/claude-setup-prompt.md`](claude-setup-prompt.md) | AI-assisted setup prompts for Claude Code |

---

*This guide is maintained by the ChapsMind Engineering Team.*
