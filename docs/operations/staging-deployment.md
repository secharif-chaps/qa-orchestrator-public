# Staging Deployment Documentation

## Overview

The staging deployment system automatically creates isolated staging environments for each merge request (MR). When a merge request is opened, a complete staging environment is deployed with its own subdomain, allowing developers and testers to preview changes before merging to the main branch.

## How It Works

### Automatic Environment Creation

1. **Trigger**: When a merge request is created, the GitLab CI/CD pipeline automatically triggers the staging deployment
2. **Subdomain Generation**: The system generates a unique subdomain based on:
   - JIRA ticket number (extracted from branch name like `tar-123`)
   - Merge request ID (fallback: `mr-{id}`)
3. **Environment URL**: `https://{subdomain}.staging.target.localnet`

### Architecture

The staging environment includes:

- **API**: Symfony application with FrankenPHP
- **PWA**: Nuxt.js frontend application
- **Database**: PostgreSQL with isolated data
- **Keycloak**: Authentication service with staging realm
- **n8n**: Workflow automation
- **RabbitMQ**: Message queue
- **Traefik**: Reverse proxy and SSL termination (configured on staging server)

## Configuration Files

### Primary Configuration

| File                   | Purpose                             | Key Changes                                       |
| ---------------------- | ----------------------------------- | ------------------------------------------------- |
| `.gitlab-ci.yml`       | CI/CD pipeline definition           | Staging deploy/cleanup jobs                       |
| `compose.staging.yaml` | Docker Compose override for staging | Resource limits, networking, labels (for Traefik) |
| `compose.yaml`         | Base Docker Compose configuration   | Service definitions                               |

### Application Configuration

Staging environments are deployed for the backend with the dedicated configuration `APP_ENV=staging`

| File                                | Purpose                                 |
| ----------------------------------- | --------------------------------------- |
| `api/config/bundles.php`            | Symfony bundles for staging environment |
| `api/config/packages/*.yaml`        | Symfony configuration per environment   |
| `api/frankenphp/Caddyfile`          | Web server configuration                |
| `api/frankenphp/keycloak.Caddyfile` | Keycloak reverse proxy                  |
| `api/frankenphp/n8n.Caddyfile`      | n8n reverse proxy                       |

### Environment Variables

Key environment variables are generated dynamically in the CI pipeline (see `.gitlab-ci.yml`):

```bash
# Core configuration
SUB_DOMAIN={jira-ticket|mr-id}
BASE_DOMAIN=staging.target.localnet
DYNAMIC_ENVIRONMENT_URL=https://{SUB_DOMAIN}.{BASE_DOMAIN}

# Service endpoints
SERVER_NAME={SUB_DOMAIN}.{BASE_DOMAIN}
KEYCLOAK_SERVER_NAME=auth-{SUB_DOMAIN}.{BASE_DOMAIN}
N8N_SERVER_NAME=n8n-{SUB_DOMAIN}.{BASE_DOMAIN}

# Security
APP_SECRET={randomly-generated}
POSTGRES_PASSWORD={randomly-generated}
KEYCLOAK_DB_PASSWORD={randomly-generated}
RABBITMQ_PASSWORD={randomly-generated}
```

## Deployment Process

### 1. Environment Preparation

The `staging-deploy` job in GitLab CI:

1. **Authentication**: Logs into Docker registry
2. **Certificate Setup**: Copy CA certificates for SSL from GitLab CI/CD configuration variables (<https://git.mediaspeech.com/basil/basil/-/settings/ci_cd>)
3. **Configuration Generation**:
   - Extracts JIRA ticket from branch name
   - Generates unique subdomain
   - Creates environment variables
   - Configures Keycloak realm
4. **Artifact Creation**: Prepares deployment package

### 2. Service Deployment

The `auto-deploy-staging.py` script is dedicated to our use. It is written in python and referenced in the CI image repository (<https://git.mediaspeech.com/basil/ci-images/-/blob/main/auto-deploy/auto-deploy-staging.py?ref_type=heads>). This script is responsible for:

1. **Environment Validation**: Checks configuration
2. **Docker Deployment**: Starts services with Docker Compose
3. **Traefik Integration**: Registers routes and SSL certificates
4. **Health Checks**: Verifies service availability
5. **GitLab Environment**: Updates MR with environment URL
6. **Jira Integration**: Updates JIRA ticket with staging URL
7. **Slack Notification**: Sends deployment notification to the `#team-target-gitlab` channel

### 3. Cleanup Process

The `staging-stop` job (manual trigger), through the `auto-deploy-staging.py` script, is responsible for:

1. **Service Removal**: Stops and removes Docker containers
2. **Resource Cleanup**: Removes volumes and networks
3. **Traefik Deregistration**: Removes routing rules
4. **Environment Closure**: Marks GitLab environment as stopped
5. **Jira Integration**: Updates JIRA ticket with staging URL
6. **Slack Notification**: Sends deployment notification to the `#team-target-gitlab` channel

### 4. Periodic Cleanup

The `staging-cleanup` job is scheduled to run periodically (e.g., weekly), through the `auto-deploy-staging.py` script, to:

1. **Identify Stale Environments**: Checks for completed/closed/rejected Jira Ticket, MR closed or inactive environments (older than 7 days)
2. **Remove Resources**: Cleans up Docker containers, volumes, and networks
3. **Traefik Deregistration**: Removes routing rules
4. **Update GitLab**: Marks environments as stopped
5. **Notify Team**: Sends cleanup notification to the `#team-target-gitlab` channel
6. **Jira Integration**: Updates JIRA ticket with staging URL

## Infrastructure Requirements

### Server Configuration

- **Tags**: Runners must have the `staging` tag
- **Docker**: Docker and Docker Compose installed
- **Network**: Access to `staging.target.localnet` domain
- **Storage**: Sufficient disk space for multiple environments

### Network Setup

```yaml
# Traefik configuration required for:
- SSL certificate management
- Dynamic routing (Host-based)
- Load balancing with health checks
```

### Resource Limits

Each staging environment is limited to:

- **High-resource services**: 1GB RAM, 2 CPU cores (API, Database, n8n)
- **Medium-resource services**: 1GB RAM, 1 CPU core (PWA, Keycloak, RabbitMQ, Runner)

## Maintenance Guide

### Monitoring Staging Environments

1. **GitLab Environments**: Check active environments in GitLab project
2. **Server Resources**: Monitor disk space and memory usage
3. **Docker Containers**: `docker ps` to see running containers
4. **Logs**: `docker logs <container>` for troubleshooting

### Common Maintenance Tasks

#### Cleanup Stale Environments

```bash
# List all staging environments
docker ps | grep staging

# Stop specific environment
cd /opt/target/staging/{environment}
docker compose down -v
```

#### Update Base Images

```bash
# Pull latest images
docker compose pull

# Restart environments (done automatically on next deployment)
```

### Troubleshooting

#### Common Issues

| Issue                 | Symptoms                  | Solution                                      |
| --------------------- | ------------------------- | --------------------------------------------- |
| SSL Certificate Error | Browser security warnings | Check CA certificate configuration            |
| Service Not Starting  | 502/503 errors            | Check Docker logs and resource limits         |
| Database Connection   | API errors                | Verify PostgreSQL container and credentials   |
| Authentication Issues | Login failures            | Check Keycloak configuration and realm import |

#### Debug Commands

```bash
# Check environment status
docker compose ps

# View service logs
docker compose logs -f api
docker compose logs -f pwa

# Test internal connectivity
docker compose exec api curl http://keycloak:8080/health

# Validate Traefik routing
curl -H "Host: {subdomain}.staging.target.localnet" http://staging-server/api/healthcheck
```

### Security Considerations

1. **Environment Isolation**: Each staging environment is isolated with unique credentials
2. **SSL/TLS**: All traffic encrypted with proper certificates
3. **Access Control**: Staging domains should be restricted to internal networks
4. **Secrets Management**: Passwords auto-generated per environment
5. **Cleanup**: Automatic cleanup prevents credential accumulation

### Performance Optimization

1. **Resource Monitoring**: Monitor staging server resources
2. **Image Caching**: Reuse Docker images across environments
3. **Volume Management**: Clean up unused volumes regularly
4. **Network Optimization**: Use dedicated staging network

## Key Scripts and Tools

### Auto-Deploy Script

Location: `/app/auto-deploy-staging.py` (in CI container)

Key functions:

- `deploy`: Create/update staging environment
- `clean`: Remove staging environment
- Environment URL generation
- GitLab environment integration

### Health Checks

- **API**: `/api/healthcheck` endpoint
- **PWA**: `/health` endpoint
- **Docker**: Container-level health checks with configurable timeouts

## Best Practices

1. **Branch Naming**: Use JIRA ticket format (`tar-123-feature-name`) for automatic subdomain generation
2. **Resource Usage**: Monitor staging server capacity
3. **Cleanup**: Manually trigger cleanup for closed MRs
4. **Testing**: Use staging environments for integration testing
5. **Documentation**: Keep environment variables documented

## Support and Maintenance

For issues or questions:

1. Check GitLab CI/CD logs
2. Review Docker container logs
3. Verify server resources and connectivity
4. Contact DevOps team for infrastructure issues
