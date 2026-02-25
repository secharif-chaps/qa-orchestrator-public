# Tech Stack

## Overview

ChapsMind is built as a modern, AI-first market and economic intelligence platform with a clear separation between frontend, backend services, AI/workflow orchestration, and infrastructure.

---

## Frontend

### Current Stack

| Category | Technology | Notes |
|----------|------------|-------|
| **Framework** | Vue 3 | Composition API with `<script setup lang="ts">` |
| **Language** | TypeScript | Strict mode enabled |
| **Build Tool** | Vite 7 | Fast HMR, optimized builds |
| **Styling** | Tailwind CSS v4 | Utility-first, semantic color tokens |
| **UI Components** | Custom + Reka UI | Design system with accessibility focus |
| **State Management** | Pinia | Global state with persistence plugin |
| **Data Fetching** | Pinia Colada | Queries and mutations with caching |
| **Routing** | Vue Router + unplugin-vue-router | File-based routing |
| **Internationalization** | vue-i18n | Multi-language support |
| **Charts** | Chart.js + vue-chartjs | Data visualization |
| **Flow Diagrams** | Vue Flow | Graph-based visualizations |

### Planned Changes

| Phase | Change | Timeline |
|-------|--------|----------|
| **Phase 2** | Switch to Vuellar UI (@owlint/feathers-vue) | Q1 2025 |
| **Phase 3** | Migrate to Nuxt.js | Q2 2025 |

### Package Manager
- **pnpm** for dependency management

### Code Quality
- **ESLint** with Vue and TypeScript plugins
- **Prettier** for code formatting
- **vue-tsc** for type checking

---

## Backend

### Core Framework

| Category | Technology | Version | Notes |
|----------|------------|---------|-------|
| **Framework** | FastAPI | >=0.115 | Async Python web framework |
| **Language** | Python | ^3.9 | Type hints throughout |
| **ASGI Server** | Uvicorn | 0.22.0 | Production server |
| **ORM** | SQLAlchemy | >=2.0.35 | Async support with asyncpg |
| **Migrations** | Alembic | 1.11.1 | Database schema versioning |
| **Validation** | Pydantic | >=2.10 | Request/response validation |

### Database

| Category | Technology | Notes |
|----------|------------|-------|
| **Primary Database** | PostgreSQL | Main data store |
| **Async Driver** | asyncpg | >=0.30.0 |
| **Sync Driver** | psycopg2-binary | >=2.9.10 |

### Authentication

| Category | Technology | Notes |
|----------|------------|-------|
| **Identity Provider** | Keycloak | Organizations for multi-tenancy |
| **Backend Integration** | fastapi-keycloak | 1.1.1 |
| **Keycloak Client** | python-keycloak | ^3.9.1 |
| **JWT Handling** | PyJWT + python-jose | Token validation |

### Task Queue

| Category | Technology | Notes |
|----------|------------|-------|
| **Task Queue** | Celery | ^5.3.4 |
| **Message Broker** | RabbitMQ | Via Kombu ^5.3.4 |
| **Monitoring** | Flower | ^2.0.1 |

### HTTP Clients

| Category | Technology | Notes |
|----------|------------|-------|
| **Sync HTTP** | httpx | 0.24.1 |
| **Async HTTP** | aiohttp | ^3.12.7 |

### Architecture Evolution (Q2 2025)

The backend will evolve into a multi-service architecture:

**Global Services:**
- Token Management Service
- User/Team/Organization Service

**Module-Specific Services:**
- Screen Core Service
- Target Core Service
- (Future) Explore, Stream, Discover services

---

## AI & Workflow Orchestration

### AI Platform

| Category | Technology | Notes |
|----------|------------|-------|
| **AI Platform** | Dify | Core AI orchestration |
| **Dify Client** | dify-client | ^0.1.10 |

### Workflow Architecture

```
Company Creation
       |
       v
+------------------+
| Dify Datacollector|  <-- Runs FIRST to gather raw data
+------------------+
       |
       v
+------------------+
| dify Workflows    |  <-- Section-specific processing
+------------------+
  |  |  |  |  |
  v  v  v  v  v
Jobs Products Team CSR News (etc.)
```

**Key Points:**
- Datacollector task runs first to find and gather raw data
- Each company section (jobs, products, team, CSR, etc.) has a dedicated workflow
- Celery orchestrates automation workflows
- Dify handles AI-powered analysis and summarization

### Task Orchestration

| Category | Technology | Notes |
|----------|------------|-------|
| **Task Queue** | Celery | Background job processing |
| **Task Monitoring** | Flower | Celery task monitoring UI |

---

## Authentication & Authorization

### Identity Management

| Category | Technology | Notes |
|----------|------------|-------|
| **Identity Provider** | Keycloak | Self-hosted |
| **Multi-Tenancy** | Keycloak Organizations | Client isolation |
| **Frontend Auth** | oidc-client | 1.11.5 |

### Permission Model

```
Organization Permissions (organization-specific):
- organization.read   : View organization content
- organization.write  : Modify organization, manage team

Company Permissions (organization-specific):
- company.view        : View companies
- company.create      : Create/search companies
- company.update      : Update companies (future)
- company.delete      : Delete companies

Admin Permissions (global):
- admin.organizations : Global organization administration
```

### Architecture Notes
- **No users table** in application database
- **No organization_members table** in application database
- All user/organization data managed in Keycloak
- Database stores Keycloak IDs as VARCHAR/UUID references

---

## Infrastructure

### Containerization

| Category | Technology | Notes |
|----------|------------|-------|
| **Containers** | Docker | Development and production |
| **Compose** | Docker Compose | Local development |
| **Orchestration** | Kubernetes | Production deployment |

### Docker Compose Files

| Environment | File | Notes |
|-------------|------|-------|
| **Development** | docker-compose.dev.yml | Local development |
| **Production** | docker-compose.prod.yml | Production builds |

### Services

| Service | Port | Description |
|---------|------|-------------|
| **Frontend** | 3000 | Vue/Nuxt application |
| **Backend** | 8000 | FastAPI API server |
| **Keycloak** | 8080 | Authentication service |
| **PostgreSQL** | 5432 | Database |
| **RabbitMQ** | 5672 | Message broker |
| **Flower** | 5555 | Celery monitoring |

### Kubernetes

| Category | Notes |
|----------|-------|
| **Namespace** | chapsmind |
| **Backend Pods** | mint-backend-* |
| **Database Pods** | postgres-* |

---

## Testing

### Backend Testing

| Category | Technology | Notes |
|----------|------------|-------|
| **Framework** | pytest | ^7.4.0 |
| **Async Support** | pytest-asyncio | ^0.21.1 |
| **Coverage** | pytest-cov | ^4.1.0 |

### Frontend Testing

| Category | Technology | Notes |
|----------|------------|-------|
| **Unit Tests** | Vitest | (planned) |
| **E2E Tests** | Playwright | (planned) |

---

## Development Tools

### Version Control

| Category | Technology | Notes |
|----------|------------|-------|
| **VCS** | Git | Feature branch workflow |
| **CI/CD** | GitLab CI | .gitlab-ci.yml |
| **Commit Style** | Gitmoji + Conventional | Visual commit messages |

### Code Quality

| Category | Frontend | Backend |
|----------|----------|---------|
| **Linting** | ESLint | Ruff |
| **Formatting** | Prettier | Ruff |
| **Type Checking** | vue-tsc | mypy (planned) |

---

## Third-Party Integrations

### Current

| Service | Purpose |
|---------|---------|
| **Dify** | AI platform for intelligent analysis and workflow orchestration |
| **Keycloak** | Identity and access management |

### Planned

| Service | Purpose | Timeline |
|---------|---------|----------|
| **Slack** | Intelligence distribution (Stream module) | Future |
| **Email Service** | Newsletter delivery | Future |

---

## Migration Path

### Q1 2025: UI Library Migration

```
Current: Custom components + Reka UI
    |
    v
Target: Vuellar UI (@owlint/feathers-vue)
```

**Rationale:** Standardize on a comprehensive component library for faster development and consistent UX.

### Q2 2025: Framework Migration

```
Current: Vue 3 + Vite
    |
    v
Target: Nuxt.js
```

**Rationale:** Server-side rendering, better SEO, improved architecture patterns, and built-in conventions for larger applications.

### Q2 2025: Backend Service Split

```
Current: Monolithic FastAPI
    |
    v
Target: Multi-Service Architecture
    |
    +-- Global Services
    |   +-- Token Management
    |   +-- User/Team/Organization
    |
    +-- Module Services
        +-- Screen Core
        +-- Target Core
        +-- (Future modules)
```

**Rationale:** Scalability, independent deployment, and clear separation of concerns as the platform grows.
