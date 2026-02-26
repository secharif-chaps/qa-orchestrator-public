# Specification: Global Service API Gateway

## Goal

Transform the monolithic FastAPI backend into a service-oriented architecture where the Global Service acts as the single API Gateway and shared services layer, routing requests to internal module services (Screen Service) while owning cross-module functionality (tokens, folders, organizations, user management).

## User Stories

- As a frontend developer, I want a single API endpoint so that I do not need to manage multiple service URLs or authentication flows
- As a platform architect, I want module services isolated behind the gateway so that we can scale and deploy them independently while maintaining a unified security boundary

## Specific Requirements

**Global Service as API Gateway**
- Single internet-facing FastAPI service at port 8000
- All frontend requests route through Global Service
- JWT validation happens once at the gateway using Keycloak
- Request routing based on URL path prefix (`/api/screen/*` routes to Screen Service)
- Required parameters (org_id, user_id, etc.) passed per-request as needed (no standard headers)
- No separate API Gateway component (Kong/NGINX) - Global Service IS the gateway

**Internal Service Communication Protocol**
- REST for initial implementation (gateway → monolith/screen service)
- gRPC added in final phase after architecture is stable
- Gateway maintains a **protocol registry** mapping routes to supported protocols
- **Protocol selection logic**: gRPC preferred when available, REST fallback otherwise
- Goal: 100% gRPC coverage (added incrementally in Phase 6)
- Screen Service exposes REST (port 8001) initially, gRPC (port 50051) added later
- Screen Service trusts internal calls without re-validating JWT (internal network trust)
- Parameters passed per-request as needed (query params, body, gRPC message fields)

**Database Schema Separation**
- Single PostgreSQL instance with two schemas: `global_schema` and `screen_schema`
- Each service owns its schema exclusively (no cross-schema JOINs)
- Services communicate only via API calls for data from other schemas
- Alembic migrations managed separately per service with schema prefixes
- Database URL configured with `options=-c search_path=<schema>` per service

**Authentication and Authorization Flow**
- Frontend authenticates directly with Keycloak (OIDC flow)
- Global Service validates JWT on every request
- Permissions checked at Global Service based on JWT roles
- Screen Service does NOT re-validate permissions - trusts internal calls
- Organization context extracted from JWT `organization` claim

**Strangler Fig Migration Pattern (Domain-Focused)**
- Phase 0: Proxy Shell - Gateway as pure pass-through proxy to monolith
- Phase 1: Auth Layer - JWT validation and permission checking in gateway
- Phase 2: Organization Domain - Tokens, modules, org context migrated to gateway
- Phase 3: Folders Domain - Folders, items, sharing, favorites migrated to gateway
- Phase 4: Users Domain - User admin, team, account self-service migrated to gateway
- Phase 5: Screen Finalization - Monolith cleaned up and renamed to Screen Service
- Phase 6: gRPC Migration - Add gRPC protocol support, target 100% coverage
- Frontend continues calling same URLs throughout migration (transparent)
- Monolith naturally "sheds" features until only Screen remains

**Global Service Owned Features**
- Organization context (`/current`, `/activities`)
- Token management (`/organizations/{id}/tokens/*`)
- Folder system (`/folders/*` with generic item references)
- Team management (`/team/*`)
- User account self-service (`/users/me/*`)
- Global user admin (`/users/*`)
- Organization admin CRUD (`/organizations/*`)
- Module enablement (`/organizations/{id}/modules/*`)
- User preferences and AI preferences

**Screen Service Owned Features**
- Company CRUD and search (`/companies/*`)
- Task management and SSE events (`/tasks/*`)
- Workflow configuration (`/admin/workflows`)
- Dify webhooks (`/webhooks/*`)
- Chapse chatbot (`/{company_id}/chatbot`)
- Cost analysis (`/cost-analysis/*`)
- Celery workers for background task processing

**Error Handling Strategy**
- Global Service propagates errors from Screen Service directly to frontend
- HTTP status codes preserved during proxy (4xx, 5xx pass through)
- No circuit breaker patterns in initial implementation
- No caching layer in initial implementation
- Structured error responses with consistent format across services

## Existing Code to Leverage

**Current Monolith Backend (`back/`)**
- FastAPI patterns and conventions already established
- Keycloak integration via `fastapi-keycloak` library
- Pydantic schemas for request/response validation
- SQLAlchemy models with proper relationships
- Alembic migration patterns for schema management

**Organization Context Pattern (`app/core/organization.py`)**
- `OrganizationContext` dataclass extracts org_id, user_id, username from JWT
- `get_user_organization` dependency provides context to endpoints
- Reuse this pattern in Global Service, simplify in Screen Service to read headers

**Token Management Service (`app/services/token_manager.py`)**
- Complete token balance and transaction logic
- Moves entirely to Global Service without changes
- Screen Service will call Global Service API for token operations

**Folder Service (`app/services/folder.py`)**
- Folder CRUD, sharing, and access control logic
- Generic `FolderItem` with `item_id` + `item_type` already supports future modules
- Moves entirely to Global Service without changes

**Keycloak Admin Service (`app/services/keycloak_admin.py`)**
- User management, session management, activity logs
- Organization member management
- Remains in Global Service (shared service)

## Out of Scope

- Target Service integration (future module, not part of this spec)
- Explore Service preparation (future module, not part of this spec)
- Cross-module data sharing APIs (services communicate only via existing patterns)
- Circuit breaker patterns (deferred to future iteration)
- Caching layer between services (deferred to future iteration)
- Graceful degradation for service failures (simple error propagation for now)
- Rate limiting at gateway level (existing Keycloak rate limiting sufficient)
- Service discovery/registry (static configuration for two services)
- Event-driven communication (Celery remains Screen-only, no cross-service events)

---

## Architecture Reference

### Request Flow

```
Frontend (Vue.js)
     |
     | REST + JWT
     v
Global Service (port 8000)
     |
     |-- /api/screen/* --> [Protocol Registry Check]
     |                          |
     |                          |--> gRPC available? --> Screen Service (gRPC :50051)
     |                          |--> gRPC not ready? --> Screen Service (REST :8001)
     |
     |-- /api/tokens/*   --> Handle directly (global_schema)
     |-- /api/folders/*  --> Handle directly (global_schema)
     |-- /api/users/*    --> Handle directly (Keycloak Admin API)
     |-- /api/organizations/* --> Handle directly (global_schema + Keycloak)
```

### Protocol Registry

The gateway maintains a registry of which routes support gRPC:

```python
# Example protocol registry structure
PROTOCOL_REGISTRY = {
    "screen": {
        "GET /companies": "grpc",      # gRPC ready
        "POST /companies": "grpc",     # gRPC ready
        "GET /companies/{id}": "rest", # Still on REST
        "POST /tasks": "rest",         # Still on REST
        # ... routes added as gRPC is implemented
    }
}
```

**Protocol selection**: When routing a request, the gateway checks the registry. If the route has `grpc` protocol, use gRPC client. Otherwise, fall back to REST client.

### Database Schema Distribution

**global_schema (Global Service)**
| Table | Description |
|-------|-------------|
| `organizations` | Organization settings, token_balance |
| `token_transactions` | Token transaction audit log |
| `organization_modules` | Module enablement per organization |
| `folders` | Folder metadata and ownership |
| `folder_items` | Generic item references (id + type) |
| `folder_shares` | User-level folder sharing |
| `user_folder_favorites` | User folder favorites |
| `user_preferences` | User preferences (AI settings, etc.) |

**screen_schema (Screen Service)**
| Table | Description |
|-------|-------------|
| `companies` | Company core data |
| `company_profile` | Company profile section |
| `company_digital` | Company digital presence |
| `company_timeline` | Company timeline section |
| `company_products` | Company products section |
| `company_jobs` | Company jobs section |
| `company_csr` | Company CSR section |
| `company_press` | Company press section |
| `company_*` (children) | All 1:N child tables |
| `tasks` | Task queue and status |
| `task_dependencies` | Task dependency graph |
| `workflow_configs` | Dify workflow configuration |
| `chapse_conversation_context` | Chatbot context storage |

### Endpoint Distribution Reference

**Global Service Direct Endpoints**
| Endpoint | Method | Description |
|----------|--------|-------------|
| `/current` | GET | Current organization context |
| `/activities` | GET | Recent organization activities |
| `/organizations/{id}/tokens` | GET, POST | Token balance management |
| `/organizations/{id}/tokens/history` | GET | Transaction history |
| `/folders` | GET, POST | Folder list and creation |
| `/folders/{id}` | GET, PUT, DELETE | Folder CRUD |
| `/folders/{id}/items` | GET, POST, DELETE | Folder items |
| `/folders/{id}/shares` | GET, POST, DELETE | Folder sharing |
| `/team` | GET, POST, PUT | Team member management |
| `/users/me/sessions` | GET, DELETE | User session management |
| `/users/me/events` | GET | User activity events |
| `/users` | GET, POST, PUT | Global user admin |
| `/organizations` | GET, POST, PUT | Organization admin |
| `/organizations/{id}/modules` | GET, PUT | Module enablement |
| `/ai-preferences` | GET, PUT | AI preferences |

**Screen Service Proxied Endpoints**
| Endpoint | Method | Description |
|----------|--------|-------------|
| `/companies` | GET, POST | Company list and creation |
| `/companies/search` | POST | Company search |
| `/companies/{id}` | GET, PUT, DELETE | Company CRUD |
| `/companies/{id}/tasks` | GET, POST | Company tasks |
| `/companies/{id}/chatbot` | POST | Chapse chatbot |
| `/tasks` | GET | Task list |
| `/tasks/{id}` | GET, PUT | Task management |
| `/tasks/events` | GET (SSE) | Task status events |
| `/admin/workflows` | GET, POST, PUT | Workflow config |
| `/webhooks/dify` | POST | Dify callbacks |
| `/cost-analysis` | GET | Cost analysis data |

### Migration Phase Plan (Domain-Focused)

**Phase 0: Proxy Shell** (1 sprint)
- Create new Global Service FastAPI project
- Implement pure REST proxy (forward all requests to monolith unchanged)
- Create `global_schema` and `screen_schema` (empty initially)
- Deploy gateway alongside monolith, update nginx/ingress
- Exit: Frontend calls gateway URL, everything works identically

**Phase 1: Auth Layer** (1 sprint)
- Implement JWT validation middleware in gateway (Keycloak)
- Implement permission checking logic in gateway
- Update monolith to trust internal calls (skip JWT validation when from gateway)
- Exit: Auth happens once at gateway, monolith doesn't re-validate

**Phase 2: Organization Domain** (1-2 sprints)
- Migrate `organizations`, `token_transactions`, `organization_modules` tables to `global_schema`
- Implement token management endpoints in gateway (`/organizations/{id}/tokens/*`)
- Implement org context endpoints in gateway (`/current`, `/activities`)
- Implement module enablement endpoints (`/organizations/{id}/modules/*`)
- Remove migrated code from monolith, update proxy routing
- Exit: Organization domain fully owned by gateway

**Phase 3: Folders Domain** (1-2 sprints)
- Migrate `folders`, `folder_items`, `folder_shares`, `user_folder_favorites` tables to `global_schema`
- Implement folders CRUD endpoints in gateway
- Implement folder items endpoints (generic `{id, type}` design)
- Implement folder sharing and favorites endpoints
- Remove migrated code from monolith
- Exit: Folders domain fully owned by gateway

**Phase 4: Users Domain** (1-2 sprints)
- Migrate `user_preferences` table to `global_schema`
- Implement global user admin endpoints (`/users/*`) in gateway
- Implement team management endpoints (`/team/*`) in gateway
- Implement account self-service (`/users/me/*`) in gateway
- Remove migrated code from monolith
- Exit: Users domain fully owned by gateway

**Phase 5: Screen Finalization** (1 sprint)
- Migrate remaining tables to `screen_schema` (companies, tasks, etc.)
- Remove all global code from monolith
- Rename monolith → `screen-service`, update Docker/CI
- Update gateway routing for screen endpoints
- Exit: Clean separation - Gateway owns global, Screen Service owns screen

**Phase 6: gRPC Migration** (2 sprints)
- Implement protocol registry in gateway
- Add gRPC client to gateway
- Add gRPC server to Screen Service
- Define gRPC services for all screen endpoints
- Incrementally migrate routes from REST to gRPC
- Exit: 100% gRPC coverage for gateway → screen communication
