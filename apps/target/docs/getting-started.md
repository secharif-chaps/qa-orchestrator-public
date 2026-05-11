# Getting Started

This comprehensive guide will help you set up the Basil development environment and get the application running locally.

## 📋 Prerequisites

Before starting, ensure you have the following installed on your system:

### Required Software

- **Docker Desktop** (latest stable version)
  - Windows: [Download Docker Desktop](https://docs.docker.com/desktop/windows/install/)
  - macOS: [Download Docker Desktop](https://docs.docker.com/desktop/mac/install/)
  - Linux: [Install Docker Engine](https://docs.docker.com/engine/install/)
- **Git** (version 2.9 or higher)
- **Node.js** (version 18 or higher) - for local development tools
- **Taskfile** (recommended) - [Installation guide](https://taskfile.dev/installation/)

### System Requirements

- **Memory**: 8GB RAM minimum, 16GB recommended
- **Storage**: 10GB free disk space
- **OS**: Windows 10/11, macOS 10.15+, or Linux (Ubuntu 18.04+)

### Verification

Verify your installations:

```bash
docker --version         # Should show Docker version 20.0+
docker compose version   # Should show Docker Compose version 2.0+
git --version            # Should show Git version 2.9+
node --version           # Should show Node.js version 18+
task --version           # Should show Taskfile version 3+
```

## 🔄 UUID Migration Status

**Important**: This project has been migrated to use UUID primary keys for all entities. This ensures consistency, improved security, and better scalability.

### What Changed

- All entities now use UUID primary keys instead of auto-increment integers
- Gateway interfaces have been updated to use string IDs
- API Platform configurations reflect UUID usage
- Database migration preserves existing data

### For New Developers

If you're setting up the project for the first time:

1. **Database will be created with UUID schema** - no migration needed
2. **All entities use UUIDs by default** - follow the patterns in existing code
3. **API endpoints expect UUID strings** - not integer IDs

### For Existing Developers

If you have an existing database:

1. **Run the migration** to convert existing data to UUIDs
2. **Update any hardcoded integer IDs** in your code
3. **Clear cache** after the migration

## 🌐 DNS Configuration

To access the application locally via custom domain names, you must add entries to your system's hosts file.

By default, the application uses the following domains: `basil.local`, `www.basil.local`, `auth.basil.local`, `n8n.basil.local`, and `opensearch.basil.local`.

### Configure Hosts File

Edit your hosts file and add the following line:

```txt
127.0.0.1 basil.local www.basil.local auth.basil.local n8n.basil.local opensearch.basil.local
```

**File location:**

| OS          | Path                                                                  |
| ----------- | --------------------------------------------------------------------- |
| Windows     | `C:\Windows\System32\drivers\etc\hosts` (run editor as Administrator) |
| macOS/Linux | `/etc/hosts` (use `sudo nano /etc/hosts`)                             |

### Custom Domains (Optional)

These domain names are fully customizable. If you wish to use different values:

1. Update the corresponding environment variables in your `.env` file:
   - `SERVER_NAME` - Main application domain
   - `TRUSTED_HOSTS` - Comma-separated list of trusted hosts
   - `KEYCLOAK_SERVER_NAME` - Keycloak authentication domain
   - `N8N_SERVER_NAME` - N8N workflows domain
   - `OPENSEARCH_SERVER_NAME` - OpenSearch Dashboards analytics domain

2. Regenerate your SSL certificates to match the new domains (see [SSL Certificate Generation](#-ssl-certificate-generation))

3. Update your hosts file with the new domains

## ⚙️ Environment Configuration

### 1. Root Environment Variables (for Docker Compose)

Copy the main environment file:

```bash
cp .env.dist .env
```

**⚠️ Important macOS Configuration:**

If you're on macOS, you need to modify the `.env` file:

1. **Comment out the COMPOSE_BAKE line** to disable this Linux-specific feature:

   ```env
   # COMPOSE_BAKE=true
   ```

2. **Uncomment the following lines** for macOS compatibility:
   ```env
   COMPOSE_FILE=compose.yaml:compose.override.yaml:compose.mac.yaml
   APT_OPTION=--no-install-recommends
   PLATFORM=macOS
   ```

### 2. API Configuration (for Symfony Backend)

Create the API environment file:

```bash
touch api/.env.local
```

**Note:** The shared configuration can be found in Passbolt under "Basil API .env.local". Contact the team if you need access.

### 3. Frontend Configuration (for Nuxt.js Frontend)

Copy the frontend environment file:

```bash
cp pwa/.env.dist pwa/.env
```

**Note:** If you use custom domain names, update the `NUXT_PUBLIC_API_URL` and `NUXT_PUBLIC_KEYCLOAK_URL` variables in this file to match your configuration.

## 🔒 SSL Certificate Generation

Generate SSL certificates for local HTTPS development:

### Generate Certificates

```bash
./certs/self-signed-generator.sh
```

### Custom Certificate Configuration (Optional)

For custom domains, configure certificate generation:

```bash
# Copy certificate environment file
cp certs/.env.dist certs/.env

# Edit certificate configuration
nano certs/.env
```

**Certificate configuration (`certs/.env`):**

```env
PRIMARY_DOMAIN=basil.local
DOMAIN_ALIASES=www.basil.local,auth.basil.local,n8n.basil.local
```

### Trust the Certificate Authority

To avoid browser security warnings, add the generated CA certificate to your system's trust store:

**Windows:**

1. Run `certmgr.msc` as Administrator
2. Navigate to "Trusted Root Certification Authorities" > "Certificates"
3. Right-click > "All Tasks" > "Import"
4. Import `certs/ca-cert.pem`

**macOS:**

```bash
# Add to keychain
sudo security add-trusted-cert -d -r trustRoot -k /Library/Keychains/System.keychain certs/ca-cert.pem
```

**Linux (Ubuntu/Debian):**

```bash
# Copy certificate
sudo cp certs/ca-cert.pem /usr/local/share/ca-certificates/basil-ca.crt

# Update certificates
sudo update-ca-certificates
```

**Browser-specific (Chrome/Edge):**

1. Settings > Privacy and Security > Security
2. Manage certificates > Authorities
3. Import `certs/ca-cert.pem`

**Firefox:**

1. Settings > Privacy & Security
2. View Certificates > Authorities
3. Import `certs/ca-cert.pem`

## 👤 User Configuration

Configure proper file permissions for Docker containers:

**⚠️ macOS Users:**

On macOS, Docker Desktop handles user permissions differently. You should:

1. **Skip the user ID configuration steps below**
2. **Comment out the USER section** in your Docker compose configuration
3. Docker Desktop will automatically handle file permissions for you

### Check Your User IDs (Linux/WSL only)

**Note:** Skip this section if you're on macOS.

```bash
# Check your user and group IDs
echo "USER_ID=$(id -u)"
echo "GROUP_ID=$(id -g)"

# Check Docker group ID
DOCKER_GROUP_ID=$(getent group docker | cut -d: -f3)
echo "DOCKER_GROUP_ID=$DOCKER_GROUP_ID"
```

### Update Environment File (Linux/WSL only)

**Note:** Skip this section if you're on macOS.

If your IDs differ from the defaults (1000/1000/999), update your `.env` file:

```env
USER_ID=your_user_id
GROUP_ID=your_group_id
DOCKER_GROUP_ID=your_docker_group_id
```

**Why this matters:**

- Ensures files created by containers are owned by your user
- Prevents permission errors when editing files
- Allows proper Docker socket access

## 🎨 Design System Configuration

Configure access to the Feather Design System:

### Yarn Configuration

1. Copy the yarn configuration:

```bash
cd pwa
cp .yarnrc.dist.yml .yarnrc.yml
```

2. Replace placeholders in `.yarnrc.yml`:

```bash
# Edit .yarnrc.yml and replace <FEATHER_*> with actual values
nano .yarnrc.yml
```

**Note:** Feather Design System credentials can be found in the team's password manager (Passbolt).

## 🛠️ Development Tools Setup

## 🔧 Git Hooks Setup

Configure automatic code quality checks:

### Install Git Hooks

```bash
# Install and configure git hooks
task hook:install
```

This sets up:

- **Pre-commit hooks** - Run linting and formatting
- **Commit message hooks** - Enforce conventional commit format
- **Pre-push hooks** - Run tests before pushing

### Manual Hook Setup (Alternative)

If Taskfile is not available:

```bash
# Install dependencies
docker compose run --rm devtools yarn install

# Setup Husky
docker compose run --rm devtools yarn prepare
```

### Configure Lint-Staged (Optional)

Customize the linting configuration:

```bash
# Copy default configuration
cp lint-staged.config.dist.mjs lint-staged.config.mjs

# Edit configuration as needed
nano lint-staged.config.mjs
```

## 🚀 Start the Application

### Start All Services

```bash
# Start the complete development stack
docker compose up

# Or run in background
docker compose up -d

# Follow logs for all services
docker compose logs -f

# Follow logs for specific service
docker compose logs -f api
```

### Service Startup Order

The services start in this order:

1. **PostgreSQL** - Database
2. **Valkey** - Cache
3. **RabbitMQ** - Message queue
4. **OpenSearch** - Search engine
5. **Keycloak** - Authentication server
6. **API** - Symfony backend
7. **PWA** - Nuxt.js frontend
8. **Additional services** - N8N, Mailpit, OpenSearch Dashboards

### Verify Services

Check that all services are running:

```bash
# Check service status
docker compose ps

# Check service health
docker compose ps --format "table {{.Name}}\t{{.Status}}\t{{.Ports}}"
```

All services should show "Up" status. If any service shows "Exit" or "Restarting", check the logs:

```bash
docker compose logs [service-name]
```

## 🌐 Access the Application

Once all services are running, access the application:

### Main Applications

- **Frontend (PWA)**: https://basil.local
- **API Documentation**: https://basil.local/api/docs

### Development Tools

- **Dev documentation**: https://basil.local/_dev/docs
- **Keycloak Admin**: https://auth.basil.local/admin
  - Username: `basil`
  - Password: `Basil300425!`
  - See [Keycloak Guide](keycloak.md) for detailed authentication info
- **N8N Workflows**: https://n8n.basil.local
- **Mailpit (Email Testing)**: http://localhost:8025
- **OpenSearch Dashboards (Logs/Analytics)**: https://opensearch.basil.local
  - **Note**: Access requires auto-generated token service
- **RabbitMQ Management**: http://localhost:15672
  - Username: `guest`
  - Password: `guest`

### Initial Setup

1. **Load Sample Data** (optional):

```bash
# Load development fixtures
docker compose exec api php bin/console doctrine:fixtures:load --no-interaction
```

2. **Test Authentication**:
   - Visit https://basil.local
   - Click "Sign In"
   - Use created admin credentials

## 🧪 Verify Installation

### Run Health Checks

```bash
# Check API health
curl -k https://basil.local/api/healthcheck

# Check frontend
curl -k https://basil.local

# Run API tests only
task api:test

# Run frontend tests only
task pwa:test
```

### Common Verification Steps

1. **Database Connection**:

```bash
docker compose exec api php bin/console doctrine:migrations:status
```

2. **Cache Working**:

```bash
docker compose exec valkey valkey-cli ping
```

3. **Message Queue**:

```bash
docker compose exec rabbitmq rabbitmqctl status
```

4. **Search Engine**:

```bash
curl http://localhost:9200/_cluster/health
```

## 🛑 Stop the Application

### Stop Services

```bash
# Stop all services (keeps data)
docker compose down

# Stop and remove volumes (deletes data)
docker compose down -v

# Stop and remove everything including images
docker compose down -v --rmi all
```

### Clean Up Development Environment

```bash
# Remove stopped containers and unused resources
docker system prune

# Remove all unused volumes
docker volume prune

# Complete cleanup (use with caution)
docker system prune -a --volumes
```

## 🔍 Troubleshooting

### Common Issues

1. **Port Conflicts**:

```bash
# Check what's using ports
sudo lsof -i :443
sudo lsof -i :80

# Kill conflicting processes
sudo kill -9 <PID>
```

2. **Permission Issues**:

```bash
# Fix file permissions
sudo chown -R $USER:$USER .

# Update USER_ID and GROUP_ID in .env
echo "USER_ID=$(id -u)" >> .env
echo "GROUP_ID=$(id -g)" >> .env
```

3. **DNS Issues**:

```bash
# Clear DNS cache
# Windows:
ipconfig /flushdns

# macOS:
sudo dscacheutil -flushcache

# Linux:
sudo systemctl restart systemd-resolved
```

4. **SSL Certificate Issues**:

```bash
# Regenerate certificates
rm -rf certs/*.pem certs/*.key
./certs/self-signed-generator.sh

# Re-import CA certificate
```

### Get Help

If you encounter issues:

1. Check the [Troubleshooting Guide](troubleshooting.md)
2. Review service logs: `docker compose logs [service]`
3. Verify your environment configuration
4. Check that all prerequisites are installed
5. Ensure your hosts file is correctly configured

### Useful Debug Commands

```bash
# Check environment variables
docker compose config

# Inspect service configuration
docker compose ps --format json

# Check container resource usage
docker stats

# Access container shell
docker compose exec api bash
docker compose exec pwa sh
```

## 📚 Next Steps

Once your development environment is running:

1. **Read the Documentation**:
   - [Development Guide](development-guide.md)
   - [Testing Guide](testing.md)
   - [Frontend Components](frontend/components.md)
   - [API Documentation](https://basil.local/api/docs)

2. **Explore the Codebase**:
   - Backend: `api/src/`
   - Frontend: `pwa/`
   - Tests: `api/tests/`, `pwa/**/__tests__/`, `e2e/tests/`

3. **Start Developing**:
   - Create a new feature branch
   - Make your changes
   - Run tests: `task api:test` or `task pwa:test`
   - Lint and format code: `task lint`
   - Commit with conventional commits
   - Push and create a pull request

4. **Learn the Tools**:
   - [PhpStorm Setup](tools/phpstorm-setup.md)
   - [Xdebug Configuration](tools/xdebug.md)
   - [Debugging Guide](debugging.md)

Welcome to Basil development! 🌿
