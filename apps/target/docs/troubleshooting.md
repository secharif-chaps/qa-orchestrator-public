# Troubleshooting Guide

This guide covers common issues you might encounter while developing or running the Basil application and their solutions.

## 🚀 Getting Started Issues

### Docker and Environment Setup

**Issue: "Cannot connect to the Docker daemon"**

```bash
# Solution: Ensure Docker is running
sudo systemctl start docker

# Or on Windows
# Start Docker Desktop application
```

**Issue: "permission denied while trying to connect to the Docker daemon"**

```bash
#Solution: Checking the docker group ID
DOCKER_GROUP_ID=$(getent group docker | cut -d: -f3)
echo "DOCKER_GROUP_ID=$DOCKER_GROUP_ID"
#if not 999, add it in .env and not present in the .env file
echo DOCKER_GROUP_ID=$(getent group docker | cut -d: -f3) >> .env
docker compose build devtools
```

**Issue: "Port already in use" errors**

```bash
# Solution: Check what's using the port
sudo lsof -i :443  # For HTTPS
sudo lsof -i :80   # For HTTP

# Kill the process using the port
sudo kill -9 <PID>

# Or change ports in compose.yaml
```

**Issue: Permission denied errors**

```bash
# Solution: Fix user permissions
echo "USER_ID=$(id -u)" >> .env
echo "GROUP_ID=$(id -g)" >> .env

# Rebuild containers
docker compose down
docker compose up --build
```

**Issue: SSL certificate errors**

```bash
# Solution: Regenerate certificates
cd certs
./self-signed-generator.sh

# Ensure certificates are trusted in your browser/system
```

**Issue: "hosts file not working"**

```bash
# Solution: Verify hosts file entries
# Windows: C:\Windows\System32\drivers\etc\hosts
# Linux/Mac: /etc/hosts

# Should contain:
# 127.0.0.1 basil.local www.basil.local auth.basil.local n8n.basil.local

# Flush DNS cache
# Windows:
ipconfig /flushdns

# macOS:
sudo dscacheutil -flushcache

# Linux:
sudo systemctl restart systemd-resolved
```

### Container Issues

**Issue: Containers fail to start**

```bash
# Solution: Check logs
docker compose logs [service-name]

# Common fixes:
# 1. Clear Docker cache
docker system prune -a

# 2. Remove volumes (WARNING: This deletes data)
docker compose down -v

# 3. Rebuild from scratch
docker compose down
docker compose build --no-cache
docker compose up
```

**Issue: "No space left on device"**

```bash
# Solution: Clean up Docker resources
docker system df  # Check disk usage
docker system prune -a  # Remove unused images
docker volume prune  # Remove unused volumes
```

## API Issues

### Symfony Application

**Issue: "Class not found" errors**

```bash
# Solution: Clear cache and dump autoload
docker compose exec api php bin/console cache:clear
docker compose exec api composer dump-autoload

# If still failing, rebuild container
docker compose down
docker compose up --build api
```

**Issue: "Access denied for user" (Database)**

```bash
# Solution: Check database connection
docker compose logs postgres

# Reset database credentials
docker compose down
docker volume rm basil_postgres_data
docker compose up postgres

# Wait for database to initialize, then:
docker compose exec api php bin/console doctrine:database:create
docker compose exec api php bin/console doctrine:migrations:migrate
```

**Issue: 500 Internal Server Error**

```bash
# Solution: Check API logs
docker compose logs api

# Enable debug mode in .env
APP_ENV=dev
APP_DEBUG=true

# Check PHP error logs
docker compose exec api tail -f var/log/dev.log
```

**Issue: API Platform routes not working**

```bash
# Solution: Clear cache and check routes
docker compose exec api php bin/console cache:clear
docker compose exec api php bin/console debug:router

# Verify API Platform configuration
docker compose exec api php bin/console debug:api-platform:resources
```

### Database Issues

**Issue: Database connection timeout**

```bash
# Solution: Check if PostgreSQL is running
docker compose ps postgres

# Restart PostgreSQL
docker compose restart postgres

# Check connection from API container
docker compose exec api php bin/console doctrine:query:sql "SELECT 1"
```

**Issue: Migration failures**

```bash
# Solution: Reset migration state
docker compose exec api php bin/console doctrine:migrations:status

# If corrupted, reset database
docker compose exec api php bin/console doctrine:database:drop --force
docker compose exec api php bin/console doctrine:database:create
docker compose exec api php bin/console doctrine:migrations:migrate --no-interaction
```

**Issue: "SQLSTATE[42P01]: Undefined table"**

```bash
# Solution: Run migrations
docker compose exec api php bin/console doctrine:migrations:migrate

# If migrations are up to date but table missing:
docker compose exec api php bin/console doctrine:schema:update --force
```

## 🎨 Frontend Issues

### Nuxt.js Application

**Issue: "Module not found" errors**

```bash
# Solution: Clear node_modules and reinstall
cd pwa
rm -rf node_modules package-lock.json yarn.lock
yarn install

# Clear Nuxt cache
rm -rf .nuxt .output
npm run dev
```

**Issue: "Cannot resolve module" for Feather Design System**

```bash
# Solution: Check .npmrc and .yarnrc.yml configuration
cd pwa

# Verify .npmrc contains correct Feather tokens
cat .npmrc

# Verify .yarnrc.yml contains correct configuration
cat .yarnrc.yml

# Re-authenticate with npm/yarn
yarn npm login --registry=https://git.fenrys.io/api/v4/projects/454/packages/npm --scope=@owlint
```

**Issue: TypeScript compilation errors**

```bash
# Solution: Clear TypeScript cache
cd pwa
npx nuxi typecheck

# If errors persist, rebuild
rm -rf .nuxt node_modules
npm install
npm run dev
```

**Issue: Hydration mismatch errors**

```bash
# Solution: Check for server/client differences
# 1. Ensure data is available on both server and client
# 2. Use ClientOnly component for client-specific content
# 3. Check for dynamic imports that might cause timing issues

# Clear cache and restart
rm -rf .nuxt
npm run dev
```

### Build Issues

**Issue: Build fails with memory errors**

```bash
# Solution: Increase Node.js memory limit
cd pwa
NODE_OPTIONS="--max-old-space-size=4096" npm run build

# Or add to package.json scripts:
"build": "NODE_OPTIONS='--max-old-space-size=4096' nuxt build"
```

**Issue: CSS/SCSS compilation errors**

```bash
# Solution: Check Tailwind CSS configuration
cd pwa
npx tailwindcss build -i ./assets/css/main.css -o ./public/css/output.css

# Verify PostCSS configuration
npx postcss --config postcss.config.js
```

## 🔐 Authentication Issues

### Keycloak

**Issue: "Unable to connect to Keycloak"**

```bash
# Solution: Check Keycloak container
docker compose logs keycloak

# Verify Keycloak is accessible
curl -k https://auth.basil.local/auth/realms/chapsmind-dev

# Reset Keycloak if needed
docker compose restart keycloak
```

**Issue: "Invalid redirect URI"**

```bash
# Solution: Update Keycloak client configuration
# 1. Access Keycloak admin console: https://auth.basil.local/admin
# 2. Navigate to Clients > basil-pwa
# 3. Update Valid Redirect URIs to include your frontend URL
# 4. Save configuration
```

## 📧 Email Issues

### Mailpit

**Issue: Emails not appearing in Mailpit**

```bash
# Solution: Check Mailpit container
docker compose logs mailpit

# Verify SMTP configuration in API
# Check config/packages/mailer.yaml

# Test email sending
docker compose exec api php bin/console debug:mailer
```

**Issue: "Connection refused" to SMTP**

```bash
# Solution: Verify mailer configuration
# In api/.env.local:
MAILER_DSN=smtp://mailpit:1025

# Restart containers
docker compose restart api mailpit
```

## 🔍 Search and Analytics

### OpenSearch

**Issue: OpenSearch cluster health is red**

```bash
# Solution: Check cluster status
curl -X GET "localhost:9200/_cluster/health?pretty"

# Check node status
curl -X GET "localhost:9200/_cat/nodes?v"

# Reset OpenSearch data if corrupted
docker compose down
docker volume rm basil_opensearch_data
docker compose up opensearch
```

**Issue: "No living connections" to OpenSearch**

```bash
# Solution: Verify OpenSearch configuration
docker compose logs opensearch

# Check if OpenSearch is accessible
curl -X GET "localhost:9200/"

# Restart OpenSearch
docker compose restart opensearch
```

### OpenSearch Dashboards

**Issue: OpenSearch Dashboards won't connect to OpenSearch**

```bash
# Solution: Check OpenSearch Dashboards configuration
docker compose logs opensearch-dashboards

# Verify OpenSearch URL in Dashboards config
# Should point to http://opensearch:9200

# Wait for OpenSearch to be fully ready before starting Dashboards
docker compose up opensearch
# Wait 30 seconds
docker compose up opensearch-dashboards
```

## 🔧 Development Tools

### PhpStorm Issues

**Issue: Xdebug not working**

```bash
# Solution: Verify Xdebug configuration
docker compose exec api php -m | grep xdebug

# Check Xdebug settings
docker compose exec api php -i | grep xdebug

# Ensure PhpStorm is listening on correct port (9003)
# See docs/tools/xdebug.md for detailed setup
```

**Issue: Code completion not working**

```bash
# Solution: Rebuild PhpStorm index
# File > Invalidate Caches and Restart

# Verify Composer dependencies are indexed
# Settings > PHP > Composer > Update dependencies
```

### Git Hooks Issues

**Issue: Pre-commit hooks failing**

```bash
# Solution: Check hook installation
task hook:install

# Verify hooks are executable
ls -la .husky/

# Manual hook execution for debugging
.husky/pre-commit
```

**Issue: "Command not found" in hooks**

Run task to ensure hooks are set up correctly:

```bash
task hook:install
```

And check the directory `.husky/_` should exist and contain executable files.

## 🐳 Docker-Specific Issues

### Performance Issues

**Issue: Slow Docker performance on macOS/Windows**

```bash
# Solution: Optimize Docker settings
# 1. Increase Docker Desktop memory allocation (8GB+)
# 2. Enable VirtioFS file sharing (macOS)
# 3. Use Docker Desktop with WSL2 (Windows)

# Alternative: Use bind mounts selectively
# In compose.yaml, exclude node_modules:
volumes:
  - ./pwa:/app
  - /app/node_modules
```

**Issue: High CPU usage**

```bash
# Solution: Check which containers are consuming resources
docker stats

# Limit container resources in compose.yaml:
services:
  api:
    deploy:
      resources:
        limits:
          cpus: '0.5'
          memory: 512M
```

### Network Issues

**Issue: Services can't communicate**

```bash
# Solution: Check Docker network
docker network ls
docker network inspect basil_default

# Verify service names match compose.yaml
# Use service names for internal communication (not localhost)
```

**Issue: "Connection refused" between containers**

```bash
# Solution: Check if services are on same network
docker compose ps

# Verify port configuration
# Internal ports should be used for service-to-service communication
# External ports for host access
```

## 🔄 CI/CD Issues

### GitLab CI Issues

**Issue: Tests failing in CI but passing locally**

```bash
# Solution: Check environment differences
# 1. Verify Node.js/PHP versions match
# 2. Check environment variables
# 3. Ensure test database is properly set up

# Debug CI environment
# Add to .gitlab-ci.yml:
debug_environment:
  stage: test
  script:
    - echo "Node version:" $(node --version)
    - echo "PHP version:" $(php --version)
    - printenv | grep -E "NODE_ENV|APP_ENV"
```

**Issue: Docker build fails in CI**

```bash
# Solution: Check Docker layer caching
# Use multi-stage builds and cache optimization:

# In Dockerfile:
FROM node:18-alpine as dependencies
WORKDIR /app
COPY package*.json ./
RUN npm ci --only=production

FROM node:18-alpine as build
WORKDIR /app
COPY package*.json ./
RUN npm ci
COPY . .
RUN npm run build
```

**Issue: Pipeline timeout**

```bash
# Solution: Increase timeout in .gitlab-ci.yml
job_name:
  timeout: 30m
  script:
    - npm run test
```

## 🔧 Third-Party Service Issues

### N8N Workflow Automation

**Issue: N8N workflows not triggering**

```bash
# Solution: Check N8N logs
docker compose logs n8n

# Verify webhook URLs
# Access N8N interface: https://n8n.basil.local

# Check workflow execution history
# N8N > Executions tab
```

**Issue: N8N database connection issues**

```bash
# Solution: Reset N8N database
docker compose down
docker volume rm basil_n8n_data
docker compose up n8n

# Reimport workflows if needed
```

### Valkey Cache Issues

**Issue: Cache not working**

```bash
# Solution: Check Valkey connection
docker compose exec valkey valkey-cli ping

# Verify cache configuration in Symfony
# config/packages/cache.yaml

# Clear cache manually
docker compose exec valkey valkey-cli FLUSHALL
```

**Issue: Valkey memory usage high**

```bash
# Solution: Check Valkey memory usage
docker compose exec valkey valkey-cli INFO memory

# Set memory limit in compose.yaml:
services:
  valkey:
    command: valkey-server --maxmemory 256mb --maxmemory-policy allkeys-lru
```

## Production Issues

### SSL/TLS Issues

**Issue: SSL certificate errors in production**

```bash
# Solution: Check certificate validity
openssl x509 -in certificate.crt -text -noout

# Verify certificate chain
curl -I https://your-domain.com

# Use Let's Encrypt for production:
certbot --nginx -d your-domain.com
```

**Issue: Mixed content warnings**

```bash
# Solution: Ensure all resources use HTTPS
# Check for hardcoded HTTP URLs
grep -r "http://" pwa/

# Use relative URLs or environment-based URLs
```

### Performance Issues

**Issue: Slow API responses**

```bash
# Solution: Enable API caching
# In API Platform configuration:
# config/packages/api_platform.yaml
api_platform:
    http_cache:
        invalidation:
            enabled: true
        max_age: 3600
```

**Issue: High memory usage**

```bash
# Solution: Optimize queries and enable OPcache
# In php.ini:
opcache.enable=1
opcache.memory_consumption=256
opcache.max_accelerated_files=20000

# Use database query optimization
docker compose exec api php bin/console doctrine:query:sql "EXPLAIN ANALYZE SELECT * FROM users"
```

## 📊 Monitoring and Debugging

### Log Analysis

**Issue: Too many logs, hard to find issues**

```bash
# Solution: Use structured logging and filtering

# Filter by severity
docker compose logs api | grep ERROR

# Filter by time
docker compose logs --since="2024-01-01T12:00:00" api

# Use jq for JSON log parsing
docker compose logs api | jq '.level, .message'
```

**Issue: Application crashes without clear logs**

```bash
# Solution: Enable detailed error reporting

# In API .env:
APP_ENV=dev
APP_DEBUG=true

# In PHP configuration:
error_reporting=E_ALL
log_errors=On
display_errors=On
```

### Health Checks

**Issue: Containers appear healthy but don't work**

```bash
# Solution: Implement proper health checks
# In compose.yaml:
services:
  api:
    healthcheck:
      test: ["CMD", "curl", "-f", "http://localhost/health"]
      interval: 30s
      timeout: 10s
      retries: 3

  postgres:
    healthcheck:
      test: ["CMD-SHELL", "pg_isready -U basil"]
      interval: 30s
      timeout: 10s
      retries: 3
```

## 🆘 Emergency Procedures

### Complete Reset

**Issue: Nothing works, need to start fresh**

```bash
# Nuclear option: Complete reset
docker compose down -v
docker system prune -a --volumes
rm -rf api/var/cache/* api/var/log/*
rm -rf pwa/.nuxt pwa/.output pwa/node_modules
rm -rf .cache

# Rebuild everything
docker compose build --no-cache
docker compose up

# Reinstall dependencies
task api:composer:install
cd pwa && npm install
```

### Data Recovery

**Issue: Database data lost**

```bash
# Solution: Restore from backup
# If you have a backup:
docker compose exec postgres psql -U basil -d basil < backup.sql

# If no backup, recreate with fixtures:
docker compose exec api php bin/console doctrine:fixtures:load --no-interaction
```

### Service Recovery

**Issue: Critical service down in production**

```bash
# Solution: Quick service restart
docker compose restart [service-name]

# If that fails, rollback to previous version
git checkout [previous-stable-commit]
docker compose up --build

# Monitor service health
watch docker compose ps
```

## 📞 Getting Help

### Before Asking for Help

1. **Check this troubleshooting guide**
2. **Search existing issues** in the repository
3. **Check service logs** for error messages
4. **Try basic solutions** (restart, clear cache, rebuild)
5. **Create minimal reproduction** of the issue

### Information to Include

When reporting issues, include:

- **Environment details** (OS, Docker version, etc.)
- **Steps to reproduce** the issue
- **Error messages** with full stack traces
- **Service logs** relevant to the issue
- **Configuration files** that might be relevant
- **What you've already tried**

### Useful Debug Commands

```bash
# System information
docker --version
docker compose --version
node --version
php --version

# Service status
docker compose ps
docker compose logs --tail=50

# Resource usage
docker stats --no-stream
df -h
free -h

# Network connectivity
curl -I https://basil.local
```

### Log Collection Script

```bash
#!/bin/bash
# collect-logs.sh - Collect diagnostic information

echo "=== System Information ===" > debug-info.txt
docker --version >> debug-info.txt
docker compose --version >> debug-info.txt
uname -a >> debug-info.txt

echo "=== Service Status ===" >> debug-info.txt
docker compose ps >> debug-info.txt

echo "=== Recent Logs ===" >> debug-info.txt
docker compose logs --tail=100 >> debug-info.txt

echo "Debug information collected in debug-info.txt"
```

## 🔄 UUID-Related Issues

### "Undefined type" Errors for Classes

**Problem**: PHPStan or IDE reports "Undefined type" for classes like `UserAccessibleFolderFilter` or `FolderUserProvider`.

**Solution**:

```bash
# Regenerate autoloader
docker compose exec api composer dump-autoload

# Clear Symfony cache
docker compose exec api php bin/console cache:clear
```

**Cause**: Cache or autoloader issues after UUID migration changes.

### PHPStan Property Type Errors

**Problem**: PHPStan reports property type mismatch in test gateways.

**Error Example**:

```
Property App\Tests\Infrastructure\Actor\NullActorGateway::$actors (array<''|int, App\Domain\Actor\Actor>)
does not accept non-empty-array<int|string, App\Domain\Actor\Actor>.
```

**Solution**: Update test gateway property types to use string keys:

```php
// Before (causes error)
/**
 * @var array<int|null, Actor>
 */
private array $actors = [];

// After (correct)
/**
 * @var array<string, Actor>
 */
private array $actors = [];
```

### Gateway Interface Type Mismatch

**Problem**: Interface methods still expect `int` IDs but entities use UUID strings.

**Solution**: Update gateway interfaces to use string parameters:

```php
// Before
interface ActorGatewayInterface
{
    public function get(int $id): Actor;
}

// After
interface ActorGatewayInterface
{
    public function get(string $id): Actor;
}
```

### Migration Failures

**Problem**: PostgreSQL constraint errors during UUID migration.

**Solution**:

```bash
# Check migration status
docker compose exec api php bin/console doctrine:migrations:status

# If migration failed, check logs
docker compose logs api

# Reset and re-run migration
docker compose exec api php bin/console doctrine:migrations:migrate --no-interaction
```

### Frontend Type Errors

**Problem**: TypeScript errors when working with entity IDs.

**Solution**: Update type definitions to use string IDs:

```typescript
// Before
interface Entity {
    id: number
}

// After
interface Entity {
    id: string // UUID string
}
```

### API Response Format Issues

**Problem**: API responses show integer IDs instead of UUIDs.

**Solution**: Ensure entities are properly configured with UUID generation:

```php
#[ORM\Id]
#[ORM\Column(type: UuidType::NAME, unique: true)]
#[ORM\GeneratedValue(strategy: 'CUSTOM')]
#[ORM\CustomIdGenerator(class: UuidGenerator::class)]
private ?string $id = null;
```

This troubleshooting guide covers the most common issues you might encounter. Keep it updated as new issues and solutions are discovered during development and deployment.
