# Tech Stack

## Backend

| Component | Technology | Version |
|-----------|------------|---------|
| Framework | FastAPI | 0.100.0 |
| Language | Python | 3.11 |
| Database | PostgreSQL | 16 |
| ORM | SQLAlchemy | 2.0.17 |
| DB Driver (sync) | psycopg2-binary | 2.9.6 |
| DB Driver (async) | asyncpg | 0.28.0 |
| API Server | Uvicorn | 0.22.0 |
| Schema Validation | Pydantic | 2.0.2 |
| Configuration | Pydantic Settings | 2.0.1 |
| Migrations | Alembic | 1.11.1 |
| HTTP Client | httpx | 0.24.1 |
| HTTP Client (async) | aiohttp | ^3.12.7 |
| Authentication | python-keycloak | ^3.9.1 |
| JWT | python-jose | ^3.3.0 |
| Password Hashing | passlib | ^1.7.4 |
| Email Validation | email-validator | ^2.0.0 |
| Task Queue | Celery | ^5.3.4 |
| Task Monitoring | Flower | ^2.0.1 |
| Linting/Formatting | Ruff | latest |
| Dependency Manager | Poetry | latest |

---

## Frontend

| Component | Technology | Version |
|-----------|------------|---------|
| Framework | Vue 3 | 3.5.18 |
| Language | TypeScript | 5.8 |
| Build Tool | Vite | 7.0.6 |
| Node Version | | ^20.19.0 or >=22.12.0 |
| Package Manager | pnpm | latest |
| Import Strategy | ES Modules | |
| CSS Framework | Tailwind CSS | 4.1.11 |
| UI Components | Vuellar (@owlint/feathers-vue) | 0.3.11-beta |
| Headless Components | Reka UI | 2.4.1 |
| State Management | Pinia | 3.0.3 |
| State Persistence | pinia-plugin-persistedstate | ^4.4.1 |
| Data Fetching | Pinia Colada | 0.17.1 |
| Routing | Vue Router + unplugin-vue-router | 4.5.1 / 0.14.0 |
| Internationalization | Vue I18n | 11.1.11 |
| Charts | Chart.js + Vue Chart.js | 4.5.0 / 5.3.2 |
| Flow Diagrams | Vue Flow | 1.45.0 |
| Utilities | VueUse Core | 13.6.0 |
| Markdown | marked | 16.2.0 |
| OIDC Client | oidc-client | 1.11.5 |
| JWT Decode | jwt-decode | 4.0.0 |
| Linting | ESLint | 9.31.0 |
| Formatting | Prettier | 3.6.2 |
| Type Checking | Vue TSC | 3.0.4 |

---

## Authentication & Authorization

| Component | Technology |
|-----------|------------|
| Identity Provider | Keycloak 26.3 |
| Auth Protocol | OAuth 2.0 / OIDC |
| Permission System | Role-based with granular resource permissions |
| User Storage | Keycloak (no users table in app database) |
| Multi-tenancy | Keycloak Organizations |

---

## Infrastructure & Deployment

| Component | Technology |
|-----------|------------|
| Container Orchestration | Docker Compose (dev), Kubernetes (prod) |
| Database Container | postgres:16-alpine |
| Backend Container | Python 3.11-slim |
| Dev Port (Backend) | 8000 |
| Dev Port (Frontend) | 3000 |
| Auth Port | 8080 |
| Database Port | 5432 |
| Environment Config | .env files per environment |
| Deployment Strategy | Git-based workflow |

---

## Development Tools

| Component | Technology |
|-----------|------------|
| Backend Linting | Ruff |
| Frontend Linting | ESLint + Prettier |
| Type Checking (Frontend) | Vue TSC |
| Testing (Backend) | pytest, pytest-asyncio, pytest-cov |
| Testing (Frontend) | Vitest |
| E2E Testing | Playwright |

---

## Architecture Patterns

### Frontend
- Composition API with `<script setup lang="ts">`
- Vuellar components for all UI
- Pinia stores for global state
- Pinia Colada for server state (queries/mutations)
- File-based routing with unplugin-vue-router

### Backend
- FastAPI with dependency injection
- Service layer for business logic
- Pydantic schemas for validation
- SQLAlchemy ORM for database
- Alembic for migrations

### Multi-tenancy
- Organization-based with Keycloak Organizations
- No users table in database
- Username string references only

### Permissions
- Resource-based: `resource.action` format
- Route-level and component-level checks
- Backend verification before operations
