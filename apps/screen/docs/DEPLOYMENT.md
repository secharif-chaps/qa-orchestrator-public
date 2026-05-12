# Mint Preprod Deployment Guide

## Overview

This guide covers deploying the Mint application stack (Frontend, Backend, Keycloak, PostgreSQL) to a single preprod server.

## Architecture

- **Frontend**: Vue 3 app served by Nginx
- **Backend**: FastAPI Python application
- **Auth**: Keycloak for authentication/authorization
- **Database**: PostgreSQL (shared by backend and Keycloak)
- **Reverse Proxy**: Nginx routing all services

## Prerequisites

l

1. Server with Docker and Docker Compose installed
2. SSH access to the server (10.0.1.1)
3. Git access (SSH key configured for cloning repositories)

## Initial Server Setup

1. **SSH into the server:**

   ```bash
   ssh user@10.0.1.1
   ```

2. **Install Docker and Docker Compose:**

   ```bash
   # Install Docker
   curl -fsSL https://get.docker.com -o get-docker.sh
   sudo sh get-docker.sh
   sudo usermod -aG docker $USER

   # Install Docker Compose
   sudo curl -L "https://github.com/docker/compose/releases/latest/download/docker-compose-$(uname -s)-$(uname -m)" -o /usr/local/bin/docker-compose
   sudo chmod +x /usr/local/bin/docker-compose
   ```

3. **Create deployment directory:**

   ```bash
   sudo mkdir -p /home/deploy/mint-preprod
   sudo chown -R $USER:$USER /home/deploy/mint-preprod
   cd /home/deploy/mint-preprod
   ```

4. **Clone the repository:**
   ```bash
   git clone git@github.com:your-org/mint-new.git .
   # If mint-server is a separate repo
   git clone git@github.com:your-org/mint-server.git mint-server
   ```

## Deployment

### First Deployment

1. **Copy deployment files to server:**

   ```bash
   scp docker-compose.preprod.yml nginx-preprod.conf deploy-preprod.sh user@10.0.1.1:/home/deploy/mint-preprod/
   ```

2. **SSH into server and run deployment:**

   ```bash
   ssh user@10.0.1.1
   cd /home/deploy/mint-preprod
   ./deploy-preprod.sh
   ```

3. **On first run, update the `.env` file with secure passwords:**

   ```bash
   nano .env
   # Update DB_PASSWORD and KEYCLOAK_ADMIN_PASSWORD
   ```

4. **Run deployment again:**
   ```bash
   ./deploy-preprod.sh
   ```

### Subsequent Updates

For code updates without configuration changes:

```bash
ssh user@10.0.1.1
cd /home/deploy/mint-preprod
./update-preprod.sh
```

## Access Points

- **Application**: http://10.0.1.1
- **Backend API**: http://10.0.1.1/api
- **Keycloak Admin**: http://10.0.1.1/auth
  - Username: admin
  - Password: (set in .env file)

## Useful Commands

### View logs

```bash
# All services
docker-compose -f docker-compose.preprod.yml logs -f

# Specific service
docker-compose -f docker-compose.preprod.yml logs -f backend
docker-compose -f docker-compose.preprod.yml logs -f frontend
docker-compose -f docker-compose.preprod.yml logs -f keycloak
```

### Restart services

```bash
# All services
docker-compose -f docker-compose.preprod.yml restart

# Specific service
docker-compose -f docker-compose.preprod.yml restart backend
```

### Execute commands in containers

```bash
# Backend shell
docker-compose -f docker-compose.preprod.yml exec backend bash

# Database shell
docker-compose -f docker-compose.preprod.yml exec db psql -U postgres mint_db
```

### Backup database

```bash
docker-compose -f docker-compose.preprod.yml exec db pg_dump -U postgres mint_db > backup_$(date +%Y%m%d).sql
```

## Troubleshooting

### Services not starting

1. Check logs: `docker-compose -f docker-compose.preprod.yml logs`
2. Ensure all required ports are free
3. Verify .env file exists and has correct values

### Database connection issues

1. Ensure PostgreSQL is healthy: `docker-compose -f docker-compose.preprod.yml ps`
2. Check database logs: `docker-compose -f docker-compose.preprod.yml logs db`

### Keycloak issues

1. Ensure realm file exists: `mint-server/docker/keycloak/realm-mint-dev.json`
2. Check Keycloak logs: `docker-compose -f docker-compose.preprod.yml logs keycloak`

### Frontend not loading

1. Check nginx logs: `docker-compose -f docker-compose.preprod.yml logs nginx`
2. Verify frontend build: `docker-compose -f docker-compose.preprod.yml logs frontend`

## Environment Variables

Key environment variables in `.env`:

- `SERVER_IP`: The server IP address (10.0.1.1)
- `DB_PASSWORD`: PostgreSQL password
- `KEYCLOAK_ADMIN_PASSWORD`: Keycloak admin password
- `KEYCLOAK_CLIENT_SECRET`: Client secret for backend (if required)

## Security Notes

Since this is a preprod environment behind VPN:

- Using HTTP (not HTTPS) is acceptable
- Basic passwords are okay for testing
- No backup strategy required
- Logs are stored in Docker only

For production deployment, you would need:

- SSL certificates (Let's Encrypt)
- Strong passwords
- Backup strategy
- Log aggregation
- Monitoring solution
