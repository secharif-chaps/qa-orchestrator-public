# Getting Started

> **Last Updated:** 2026-03-19

Guide to set up ChapsMind on your local machine. Supports **Ubuntu**, **Windows (WSL2)**, and **macOS**.

---

## Prerequisites

Install the following tools before proceeding. Run `task doctor` at any time to verify.

| Tool               | Version | Ubuntu / WSL                                                                         | macOS                        |
|--------------------|---------|--------------------------------------------------------------------------------------|------------------------------|
| **Docker**         | Latest  | [docs.docker.com](https://docs.docker.com/engine/install/ubuntu/)                    | `brew install --cask docker` |
| **Docker Compose** | v2+     | Included with Docker                                                                 | Included with Docker Desktop |
| **Node.js**        | >= 24   | [nvm](https://github.com/nvm-sh/nvm): `nvm install 24`                               | `nvm install 24`             |
| **Corepack**       | —       | `corepack enable`                                                                    | `corepack enable`            |
| **jq**             | —       | `sudo apt install -y jq`                                                             | `brew install jq`            |
| **Task**           | —       | `sh -c "$(curl --location https://taskfile.dev/install.sh)" -- -d -b /usr/local/bin` | `brew install go-task`       |

> **Windows users:** Install [WSL 2](https://learn.microsoft.com/en-us/windows/wsl/install) first (
`wsl --install -d Ubuntu`), then follow the Ubuntu instructions inside
> WSL. [Docker Desktop](https://www.docker.com/products/docker-desktop/) with WSL 2 backend is recommended.

### SSH Access

You need SSH access to `git.mediaspeech.com`:

```bash
# Generate key (if needed)
ssh-keygen -t ed25519 -C "your.email@example.com"

# Add to GitLab > Preferences > SSH Keys
cat ~/.ssh/id_ed25519.pub

# Test
ssh -T -p 17890 git@git.mediaspeech.com
```

### GitLab Container Registry

Authenticate to pull private Docker images:

```bash
docker login registry.git.mediaspeech.com
```

Use your GitLab credentials or
a [Personal Access Token](https://git.mediaspeech.com/-/user_settings/personal_access_tokens) with `read_registry`
scope.

### Vuellar Registry Credentials

Get the private npm registry credentials from **Passbolt** (search for `CHAPSMIND_VUELLAR`). You'll need
`OWLINT_REGISTRY_URL` and `OWLINT_DEPLOY_KEY` — either set them in `.env` or have them ready for the interactive prompt
during `task init`.

---

## Quick Start

```bash
# 1. Clone
git clone ssh://git@git.mediaspeech.com:17890/chapsmind/chapsmind.git
cd chapsmind

# 2. Check prerequisites
task doctor

# 3. One-shot setup (env, deps, services, keycloak, migrations)
task init
```

That's it. `task init` handles everything:

1. Creates `.env` from template
2. Auto-generates `ENCRYPTION_KEY`, `INTERNAL_JWT_SECRET`, `TUNNEL_SUBDOMAIN`
3. Configures `apps/front/.yarnrc.yml` (prompts for Vuellar credentials if needed)
4. Installs frontend dependencies (in Docker)
5. Builds and starts all services
6. Waits for Keycloak, initializes realm/clients/users
7. Runs database migrations

---

## Daily Usage

```bash
task up          # Start all services
task down        # Stop all services
task restart     # Restart all services
task logs        # Tail all logs
```

---

## Services & URLs

| Service            | URL                       | Credentials   |
|--------------------|---------------------------|---------------|
| Application        | http://localhost          | via nginx     |
| API                | http://localhost/api      | Bearer token  |
| API Docs (Swagger) | http://localhost/api/docs | —             |
| Keycloak Admin     | http://localhost:8080     | admin / admin |
| RabbitMQ Admin     | http://localhost:15672    | guest / guest |

---

## Test Users

All test users are added to the **ChapsMind Dev** organization by `init-keycloak.sh`:

| Username          | Password      | Roles                                                 | Description               |
|-------------------|---------------|-------------------------------------------------------|---------------------------|
| `admin`           | `admin123`    | admin (composite: all roles)                          | Full access               |
| `company_manager` | `manager123`  | company.create, organization.read, organization.write | Company + team management |
| `company_viewer`  | `viewer123`   | organization.read                                     | Read-only access          |
| `no_access`       | `noaccess123` | (none)                                                | For testing 403 errors    |

---

## Available Commands

Run `task` with no arguments to see all commands.

### Infrastructure

| Command                       | Description                      |
|-------------------------------|----------------------------------|
| `task init`                   | One-shot first-time setup        |
| `task doctor`                 | Check prerequisites              |
| `task up`                     | Start all services               |
| `task down`                   | Stop all services                |
| `task restart`                | Restart all services             |
| `task logs`                   | Tail all logs                    |
| `task logs:service -- screen` | Tail logs for a specific service |

### Database

| Command               | Description                    |
|-----------------------|--------------------------------|
| `task migrate`        | Run Alembic migrations         |
| `task migrate:status` | Show current migration version |
| `task seed`           | Seed sample data               |
| `task db:shell`       | Open psql shell                |

### Frontend

| Command                | Description                  |
|------------------------|------------------------------|
| `task front:dev`       | Start dev server with HMR    |
| `task front:lint`      | Lint and fix frontend code   |
| `task front:typecheck` | Run TypeScript type checking |
| `task front:build`     | Build for production         |

### Backend (Screen)

| Command              | Description                  |
|----------------------|------------------------------|
| `task screen:lint`   | Lint backend code (ruff)     |
| `task screen:format` | Format backend code (ruff)   |
| `task screen:test`   | Run backend tests            |
| `task screen:shell`  | Open bash shell in container |

### Tunnel (Dify callbacks)

| Command            | Description             |
|--------------------|-------------------------|
| `task tunnel`      | Start localtunnel       |
| `task tunnel:stop` | Stop the tunnel         |
| `task tunnel:url`  | Show current tunnel URL |
| `task tunnel:logs` | Follow tunnel logs      |

> The tunnel is **on-demand** — not started by `task up`. Only needed when testing Dify workflow callbacks.

---

## Troubleshooting

### Clean rebuild

```bash
task down
docker compose down --rmi local
task up
task migrate
```

### Full reset (removes all data)

```bash
docker compose down -v --rmi local
task init
```

### Port conflicts

```bash
# Check what's using a port
lsof -i :80 -i :8000 -i :8080

# Kill process
kill -9 <PID>
```

### Yarn / registry issues

```bash
# Re-configure Vuellar credentials
task front:setup-yarnrc
```

### Service not responding

```bash
# Check status
task logs:service -- screen    # or keycloak, db, etc.

# Restart specific service
docker compose restart screen
```

### WSL-specific

```bash
# Docker hangs → restart WSL
wsl --shutdown

# DNS broken
sudo bash -c 'echo "nameserver 8.8.8.8" > /etc/resolv.conf'
sudo chattr +i /etc/resolv.conf
```

---

## Related Documentation

| Resource              | Location                                                              |
|-----------------------|-----------------------------------------------------------------------|
| Architecture Overview | [`docs/architecture/`](architecture/)                                 |
| Product Mission       | [`agent-os/product/mission.md`](../agent-os/product/mission.md)       |
| Tech Stack            | [`agent-os/product/tech-stack.md`](../agent-os/product/tech-stack.md) |
| Coding Standards      | [`agent-os/standards/`](../agent-os/standards/)                       |
| AI Assistant Guide    | [`CLAUDE.md`](../CLAUDE.md)                                           |
