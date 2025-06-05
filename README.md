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
- `N8N_BASE_URL`: URL for the N8N integration
- `N8N_WEBHOOK_ID`: Webhook ID for N8N integration

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