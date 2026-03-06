# Getting Started

> **Last Updated:** 2026-03-04

Step-by-step guide to set up ChapsMind on your local machine (Ubuntu / WSL / macOS).

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
  - [2. Add Legacy Remotes](#2-add-legacy-remotes)
  - [3. Fetch All Remotes](#3-fetch-all-remotes)
  - [4. Configure the Private Registry (Vuellar)](#4-configure-the-private-registry-vuellar)
  - [5. Environment Variables](#5-environment-variables)
  - [6. Initialize the Project](#6-initialize-the-project)
- [Running the Application](#running-the-application)
- [Keeping In Sync](#keeping-in-sync)
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

### 2. Add Legacy Remotes

The monorepo uses [git subtree](https://www.atlassian.com/git/tutorials/git-subtree) to sync with legacy repositories. Add the remotes:

```bash
git remote add origin-front ssh://git@git.mediaspeech.com:17890/mint/screen-front.git
git remote add origin-global-service ssh://git@git.mediaspeech.com:17890/mint/chapsmind-global-service.git
git remote add origin-infra ssh://git@git.mediaspeech.com:17890/mint/infra.git
git remote add origin-screen ssh://git@git.mediaspeech.com:17890/mint/screen-poc.git
```

### 3. Fetch All Remotes

```bash
git fetch --all
```

Then run an initial sync to pull the latest from all legacy repos:

```bash
task sync
```

> **Important:** Run `task sync` regularly to stay up-to-date with changes made in legacy repositories.

### 4. Configure the Private Registry (Vuellar)

The frontend depends on `@owlint/feathers-vue` (Vuellar), hosted on a private npm registry. You need credentials from **Passbolt**.

**Step 1:** Open Passbolt and retrieve the credentials:

> https://passbolt.localnet/app/passwords/view/4b9713f0-e896-4bc3-b5c5-4d81403a73fb

Look for the entry named **CHAPSMIND_VUELLAR**. You will need:
- `OWLINT_REGISTRY_URL` — the private registry URL
- `OWLINT_DEPLOY_KEY` — the authentication token

**Step 2:** Run the interactive setup:

```bash
task front:setup-yarnrc
```

This will prompt you for `OWLINT_REGISTRY_URL` and `OWLINT_DEPLOY_KEY`, then generate `apps/front/.yarnrc.yml` from the template.

Alternatively, set environment variables before running:

```bash
export OWLINT_REGISTRY_URL="<url-from-passbolt>"
export OWLINT_DEPLOY_KEY="<key-from-passbolt>"
task front:setup-yarnrc
```

### 5. Environment Variables

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

### 6. Initialize the Project

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

After init completes, run database migrations:

```bash
task migrate
```

---

## Running the Application

```bash
# Start all services
task up

# Open the app
# http://localhost:3000
```

To stop:

```bash
task down
```

---

## Keeping In Sync

Legacy repositories (front, screen, global-service, infra) are synced into the monorepo via `git subtree`. Run this **regularly** (e.g., at the start of each workday):

```bash
task sync
```

To sync a single module:

```bash
task sync:module -- front
task sync:module -- screen
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
| `task sync` | Pull latest from all legacy repos |

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

| Service | URL | Description |
|---------|-----|-------------|
| Frontend | http://localhost:3000 | Vue.js application |
| Backend API | http://localhost:8000/api | FastAPI endpoints |
| API Docs (Swagger) | http://localhost:8000/docs | Interactive API documentation |
| Celery Flower | http://localhost:5555 | Background task monitoring |
| RabbitMQ Admin | http://localhost:15672 | Message broker (guest / guest) |

Authentication is handled via **Keycloak** (integration server at `https://sso.dwcode.team/auth`).

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
# Make sure Docker daemon is running (Ubuntu / WSL)
sudo service docker start

# macOS / Windows: Ensure Docker Desktop is running
# macOS: open /Applications/Docker.app
# Windows: Ensure Docker Desktop is running and WSL integration is enabled
```

### Containers fail to build

```bash
# Rebuild from scratch
task down
task up
```

### Full reset (removes all data)

```bash
docker compose -f infra/compose.yaml -f infra/compose.local.yaml down -v
task up
task migrate
```

### Port conflicts

```bash
# Check what's using a port (macOS / Linux)
lsof -i :3000
lsof -i :8000

# Ubuntu / WSL alternative
ss -tlnp | grep -E '3000|8000'
```

### Yarn install fails (registry auth)

Make sure `apps/front/.yarnrc.yml` is correctly configured with valid credentials. Re-run:

```bash
task front:setup-yarnrc
```

### `task` command not found

```bash
# Ubuntu / WSL: Reinstall Task
sh -c "$(curl --location https://taskfile.dev/install.sh)" -- -d -b /usr/local/bin

# macOS: Reinstall via Homebrew
brew install go-task
```

### SSH connection refused

```bash
# Test SSH access
ssh -T -p 17890 git@git.mediaspeech.com

# If it fails, check your SSH key is added to GitLab and the agent is running
eval "$(ssh-agent -s)"
ssh-add ~/.ssh/id_ed25519
```

### `task sync` fails with merge conflicts

```bash
# Sync pulls via git subtree, which can conflict.
# Resolve conflicts manually, then commit:
git add -A
git commit -m "resolve sync conflicts"
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

---

*This guide is maintained by the ChapsMind Engineering Team.*
