# ChapsMind Project Setup Spec

Set up the ChapsMind monorepo project on my local machine following this specification.

---

## Prerequisites

Verify and install the following prerequisites:

| Tool | Required Version | Check Command |
|------|------------------|---------------|
| Docker | Latest | `docker --version` |
| Docker Compose | v2+ | `docker compose version` |
| Node.js | v22+ (via nvm) | `node --version` |
| Yarn | 4.9.1 (via Corepack) | `yarn --version` |
| Task | Latest | `task --version` |
| jq | Latest | `jq --version` |

### Installation Commands

**Windows (WSL2) - Run in PowerShell as Administrator first:**
```powershell
# Install WSL2 with Ubuntu
wsl --install -d Ubuntu

# Restart computer, then open Ubuntu terminal for remaining steps
```

**Ubuntu / WSL (run inside Ubuntu terminal):**
```bash
# Update system
sudo apt update && sudo apt upgrade -y

# Install Docker dependencies
sudo apt install -y ca-certificates curl gnupg

# Add Docker repository
sudo install -m 0755 -d /etc/apt/keyrings
curl -fsSL https://download.docker.com/linux/ubuntu/gpg | sudo gpg --dearmor -o /etc/apt/keyrings/docker.gpg
sudo chmod a+r /etc/apt/keyrings/docker.gpg
echo "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.gpg] https://download.docker.com/linux/ubuntu $(. /etc/os-release && echo "$VERSION_CODENAME") stable" | sudo tee /etc/apt/sources.list.d/docker.list > /dev/null

# Install Docker
sudo apt update
sudo apt install -y docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin

# Allow Docker without sudo (logout/login required after)
sudo usermod -aG docker $USER
newgrp docker

# Install jq
sudo apt install -y jq

# Install nvm and Node.js
curl -o- https://raw.githubusercontent.com/nvm-sh/nvm/v0.40.1/install.sh | bash
source ~/.bashrc
nvm install 22 && nvm use 22 && nvm alias default 22

# Enable Corepack (Yarn 4)
corepack enable

# Install Task
sh -c "$(curl --location https://taskfile.dev/install.sh)" -- -d -b /usr/local/bin

# Verify all tools
docker --version && docker compose version && node --version && yarn --version && task --version && jq --version
```

**macOS:**
```bash
# Install Homebrew (if not installed)
/bin/bash -c "$(curl -fsSL https://raw.githubusercontent.com/Homebrew/install/HEAD/install.sh)"

# Install Docker Desktop
brew install --cask docker
# Open Docker Desktop and wait for it to start
open /Applications/Docker.app

# Install jq and Task
brew install jq go-task

# Install nvm and Node.js
curl -o- https://raw.githubusercontent.com/nvm-sh/nvm/v0.40.1/install.sh | bash
source ~/.zshrc
nvm install 22 && nvm use 22 && nvm alias default 22

# Enable Corepack (Yarn 4)
corepack enable

# Verify all tools
docker --version && docker compose version && node --version && yarn --version && task --version && jq --version
```

---

## Project Setup Steps

### 1. Clone Repository

```bash
git clone ssh://git@git.mediaspeech.com:17890/chapsmind/chapsmind.git
cd chapsmind
```

### 2. Environment Configuration

```bash
cp .env.example .env
```

Key variables to configure in `.env`:
- `KEYCLOAK_ADMIN_CLIENT_SECRET` - Ask team member
- `DIFY_API_KEY` - Ask team member
- `ENCRYPTION_KEY` - Generate with: `python3 -c "import secrets; print(secrets.token_hex(32))"`

### 3. Configure Vuellar Private Registry

Credentials are in Passbolt under "CHAPSMIND_VUELLAR":
- `OWLINT_REGISTRY_URL` - Private npm registry URL
- `OWLINT_DEPLOY_KEY` - Authentication token

**Step 1:** Add to `.env` file (required for Docker builds):
```bash
# Edit .env and add:
OWLINT_REGISTRY_URL=<url-from-passbolt>
OWLINT_DEPLOY_KEY=<key-from-passbolt>
```

**Step 2:** Configure local frontend `.yarnrc.yml`:
```bash
task front:setup-yarnrc
```

### 4. Initialize Project

```bash
task init
```

This will:
1. Copy `.env.example` to `.env` (if not done)
2. Configure `apps/front/.yarnrc.yml`
3. Install frontend dependencies
4. Build and start Docker services

### 5. Initialize Keycloak

After Docker services are running, initialize Keycloak:

```bash
cd infra && ./scripts/init-keycloak.sh && cd ..
```

This creates the realm, OAuth clients, and test users.

### 6. Run Database Migrations

```bash
task migrate
```

### 7. Verify Setup

Check all containers are running:
```bash
docker compose -f infra/compose.yaml -f infra/compose.local.yaml ps
```

Expected containers (all healthy/running):
- `infra-db-1` - PostgreSQL
- `infra-keycloak-1` - Authentication
- `infra-rabbitmq-1` - Message broker
- `infra-screen-1` - Backend API
- `infra-screen_celery_worker-1` - Background tasks
- `infra-screen_celery_flower-1` - Task monitoring
- `infra-global-service-1` - Gateway service

---

## Service URLs

| Service | URL | Credentials |
|---------|-----|-------------|
| Frontend | http://localhost:3000 | See test users below |
| Backend API | http://localhost:8000/api | - |
| API Docs (Swagger) | http://localhost:8000/docs | - |
| Keycloak Admin | http://localhost:8080 | admin / admin |
| RabbitMQ Admin | http://localhost:15672 | guest / guest |
| Celery Flower | http://localhost:5555 | admin / admin |

---

## Test Users

All users belong to Organization 1 (ChapsVision):

| Username | Password | Permissions |
|----------|----------|-------------|
| `admin` | `admin123` | Full access |
| `company_manager` | `manager123` | company.view, company.create, company.delete |
| `company_viewer` | `viewer123` | company.view |
| `team_viewer` | `teamviewer123` | organization.read, company.view |
| `team_manager` | `teammanager123` | organization.read, organization.write, company.view |
| `no_access` | `noaccess123` | None (for testing 403 errors) |

---

## Common Commands

```bash
task              # List all available commands
task up           # Start all services
task down         # Stop all services
task restart      # Restart all services
task logs         # Tail all logs
task migrate      # Run database migrations
task front:dev    # Start frontend dev server (HMR)
task screen:shell # Open backend shell
```

---

## Troubleshooting

### Docker Issues

```bash
# Check Docker is running
sudo service docker start  # Ubuntu/WSL
# macOS: Open Docker Desktop

# Check port conflicts
lsof -i :3000
lsof -i :8000
lsof -i :8080

# Full reset (removes all data)
docker compose -f infra/compose.yaml -f infra/compose.local.yaml down -v
task up
task migrate
```

### Yarn Registry Issues

```bash
# Re-configure registry credentials
task front:setup-yarnrc

# Clear cache and retry
cd apps/front && yarn cache clean && yarn install
```

### Database Issues

```bash
# Check migration status
task migrate:status

# View database logs
task logs:service -- db

# Connect to database
task db:shell
```

### Container Issues

```bash
# View specific container logs
task logs:service -- screen
task logs:service -- keycloak

# Rebuild specific service
docker compose -f infra/compose.yaml -f infra/compose.local.yaml up -d --build screen
```

### Keycloak Issues

```bash
# Check Keycloak status
docker compose -f infra/compose.yaml -f infra/compose.local.yaml ps keycloak

# Re-run Keycloak initialization
cd infra && ./scripts/init-keycloak.sh && cd ..

# View Keycloak logs
task logs:service -- keycloak
```

### WSL-Specific Issues

```bash
# If Docker hangs, restart WSL
wsl --shutdown
# Then reopen Ubuntu terminal

# If DNS issues in WSL
sudo rm /etc/resolv.conf
sudo bash -c 'echo "nameserver 8.8.8.8" > /etc/resolv.conf'
sudo chattr +i /etc/resolv.conf

# Check WSL version (should be 2)
wsl -l -v
```

### Corepack/Yarn Issues

```bash
# If Yarn version is wrong
corepack enable
corepack prepare yarn@4.9.1 --activate

# Verify
yarn --version  # Should be 4.9.1
```
