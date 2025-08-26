# Mint Application Docker Setup

This repository contains a dockerized setup for the Mint application, consisting of a Nuxt frontend, FastAPI backend, and PostgreSQL database.

## Prerequisites

- Docker
- Docker Compose

## Getting Started

To run the application in Docker containers:

1. Clone this repository
2. Navigate to the root directory

```bash
cd mint
```

3. Build and start the containers

```bash
docker-compose up -d
```

4. Access the application

- Frontend: http://localhost:3000
- Backend API: http://localhost:8000
- Keycloak Admin Console: http://localhost:8080

## Services

- **Frontend**: Nuxt.js application running on port 3000
- **Backend**: FastAPI application running on port 8000
- **Database**: PostgreSQL running on port 5432
- **Keycloak**: Identity and Access Management server running on port 8080

## Docker Compose Commands

- Start the containers: `docker-compose up -d`
- Stop the containers: `docker-compose down`
- View logs: `docker-compose logs -f`
- Rebuild containers: `docker-compose up -d --build`

## Environment Variables

### Backend

- `DATABASE_URL`: PostgreSQL connection string
- `API_HOST`: Host to bind the API to
- `API_PORT`: Port to run the API on
- `DIFY_URL`: URL for the Dify integration
- `DIFY_API_KEY`: API key for Dify workflows

### Frontend

- `NUXT_PUBLIC_BACKEND_API`: URL of the backend API

### Keycloak

- `KEYCLOAK_DB_USERNAME`: Username for Keycloak database (default: `keycloak`)
- `KEYCLOAK_DB_PASSWORD`: Password for Keycloak database (default: `!ChangeMe!`)
- `KEYCLOAK_DB_NAME`: Name of the Keycloak database (default: `keycloak`)
- `KEYCLOAK_ADMIN_USERNAME`: Username for Keycloak admin console (default: `admin`)
- `KEYCLOAK_ADMIN_PASSWORD`: Password for Keycloak admin console (default: `admin`)

## Data Persistence

PostgreSQL data is persisted in a Docker volume named `postgres_data`. 

---

# 🚀 Production Deployment

## Initial Deployment

### Prerequisites
- SSH access to server: `nmercier@10.0.1.2`
- Docker and Docker Compose installed on server

### Deploy all services
```bash
./deploy-single-server.sh
```

This deploys all services to `10.0.1.2`:
- **Frontend**: http://10.0.1.2:3000
- **Backend API**: http://10.0.1.2:8000  
- **Keycloak**: http://10.0.1.2:8080
- **Database**: PostgreSQL on port 5432

### Create database tables (if needed)
```bash
ssh nmercier@10.0.1.2 "cd /home/nmercier/mint && docker-compose -f docker-compose.prod.yml exec backend python -c \"
import sys
sys.path.append('/app')
from app.database import Base, engine
from app.models.company import Company
from app.models.user import User  
from app.models.task import Task
print('Creating all tables...')
Base.metadata.create_all(bind=engine)
print('Tables created successfully!')
\""
```

## 🔄 Updates

### Quick update scripts
```bash
# Update only frontend (~30-60s)
./update.sh frontend

# Update only backend (~30-60s)  
./update.sh backend

# Full redeploy (~2-3min)
./update.sh all
```

### Manual updates
```bash
# Frontend only
tar -czf /tmp/mint-frontend.tar.gz mint-front/
scp /tmp/mint-frontend.tar.gz nmercier@10.0.1.2:/home/nmercier/mint/
ssh nmercier@10.0.1.2 "cd /home/nmercier/mint && tar -xzf mint-frontend.tar.gz && docker-compose -f docker-compose.prod.yml build frontend && docker-compose -f docker-compose.prod.yml up -d frontend"

# Backend only
tar -czf /tmp/mint-backend.tar.gz mint-back/
scp /tmp/mint-backend.tar.gz nmercier@10.0.1.2:/home/nmercier/mint/
ssh nmercier@10.0.1.2 "cd /home/nmercier/mint && tar -xzf mint-backend.tar.gz && docker-compose -f docker-compose.prod.yml build backend && docker-compose -f docker-compose.prod.yml up -d backend"
```

## 🔍 Monitoring

### View logs
```bash
# All logs
./logs.sh

# Specific service
./logs.sh backend
./logs.sh frontend
./logs.sh keycloak

# Real-time logs
./logs.sh backend -f

# Service status
./logs.sh status
```

### Manual monitoring
```bash
# Service status
ssh nmercier@10.0.1.2 "cd /home/nmercier/mint && docker-compose -f docker-compose.prod.yml ps"

# All logs
ssh nmercier@10.0.1.2 "cd /home/nmercier/mint && docker-compose -f docker-compose.prod.yml logs"

# Specific service logs
ssh nmercier@10.0.1.2 "cd /home/nmercier/mint && docker-compose -f docker-compose.prod.yml logs backend"

# Real-time logs
ssh nmercier@10.0.1.2 "cd /home/nmercier/mint && docker-compose -f docker-compose.prod.yml logs -f backend"
```

### Connect to containers
```bash
# Backend shell
ssh nmercier@10.0.1.2 "cd /home/nmercier/mint && docker-compose -f docker-compose.prod.yml exec backend bash"

# Database console
ssh nmercier@10.0.1.2 "cd /home/nmercier/mint && docker-compose -f docker-compose.prod.yml exec db psql -U postgres mint_db"
```

## 🔐 Authentication

### Keycloak Admin Access
- **URL**: http://10.0.1.2:8080
- **Username**: `admin`
- **Password**: `admin`

### Test Users (realm: mint-dev)
- `admin` / `password`
- `nmr` / `password`

## 🐛 Known Issues

### Alembic migrations corrupted
Migration files contain null bytes. Use manual table creation instead.

### Workflow external service
External workflow service (Dify) may be unavailable. This only affects task creation, not core functionality.

### Crypto.subtle HTTPS error
Resolved by using `oidc-client` v1.11.5 instead of `oidc-client-ts` for HTTP support.

## 📁 Project Structure

```
mint/
├── mint-front/              # Nuxt.js frontend
├── mint-back/               # FastAPI backend  
├── docker/                  # Docker configs
│   ├── db/                  # PostgreSQL init scripts
│   └── keycloak/            # Keycloak configuration
├── deploy-single-server.sh  # Full deployment script
├── update.sh                # Quick update script
├── logs.sh                  # Log viewing script
├── docker-compose.yml       # Local development
└── docker-compose.prod.yml  # Production config (generated)
```

## 🔧 Production Configuration

### Environment Variables
- `CORS_ORIGIN=http://10.0.1.2:3000` (backend)
- `NUXT_PUBLIC_BACKEND_API=http://10.0.1.2:8000` (frontend)
- `KEYCLOAK_ADMIN=admin` / `KEYCLOAK_ADMIN_PASSWORD=admin`

### Exposed Ports
- `3000`: Frontend Nuxt.js
- `8000`: Backend FastAPI
- `8080`: Keycloak
- `5432`: PostgreSQL