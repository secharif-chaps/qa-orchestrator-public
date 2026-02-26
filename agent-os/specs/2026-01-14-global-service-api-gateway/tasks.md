# Tasks: Global Service API Gateway

## Overview

Domain-focused Strangler Fig migration with 7 phases. Tasks organized for **4 developers** with parallel workstreams.

**Reference**: `user-stories.md` for detailed user stories and parallelization plan.

**Migration Pattern**: Gateway proxies to monolith initially, monolith "sheds" features until only Screen remains.

---

## Phase 0: Proxy Shell

**Goal**: Gateway exists as pure pass-through proxy to monolith
**Duration**: 1 sprint | **Devs**: 2

### 0.1 Global Service Project Setup (Dev A)
- [ ] Create `global-service/` directory with FastAPI project structure
- [ ] Set up `pyproject.toml` with dependencies (fastapi, uvicorn, httpx)
- [ ] Create `Dockerfile` for global-service
- [ ] Set up `docker-compose.yml` for local development
- [ ] Configure CI pipeline (lint, test, build)
- [ ] Create basic health check endpoint (`/health`)

### 0.2 Pure REST Proxy Implementation (Dev A)
- [ ] Implement `httpx` async client for proxying requests
- [ ] Create proxy middleware that forwards all `/api/*` requests to monolith
- [ ] Preserve request headers, body, query params in proxy
- [ ] Preserve response status codes, headers, body from monolith
- [ ] Handle streaming responses (SSE for tasks)
- [ ] Add request/response logging for debugging

### 0.3 Database Schema Creation (Dev B)
- [ ] Create Alembic configuration for global-service
- [ ] Write migration to create `global_schema` (empty)
- [ ] Write migration to create `screen_schema` (empty)
- [ ] Configure connection string with `search_path` per schema
- [ ] Test schema isolation

### 0.4 Infrastructure Deployment (Dev B)
- [ ] Update nginx/ingress to route traffic to gateway
- [ ] Configure environment variables for gateway
- [ ] Deploy gateway alongside monolith in staging
- [ ] Verify frontend can call gateway URL
- [ ] Monitor logs for proxy errors

**Tests (Phase 0)**:
- [ ] All existing API endpoints work through gateway
- [ ] Response times acceptable (< 50ms overhead)
- [ ] Error responses preserved correctly

**Exit Criteria**: Frontend calls `gateway.domain.com/api/*` and gets same responses as before.

---

## Phase 1: Auth Layer

**Goal**: Gateway validates JWT and checks permissions, monolith trusts internal calls
**Duration**: 1 sprint | **Devs**: 2

### 1.1 JWT Validation Middleware (Dev A)
- [ ] Install and configure `fastapi-keycloak`
- [ ] Create `idp` instance with Keycloak settings
- [ ] Implement JWT validation middleware
- [ ] Extract user info from JWT (sub, preferred_username, organization)
- [ ] Handle token expiration and refresh scenarios
- [ ] Return 401 for invalid/missing tokens

### 1.2 Permission Checking Logic (Dev A)
- [ ] Create permission checking utility functions
- [ ] Implement role-based access control matching monolith patterns
- [ ] Apply permission checks to proxy routes based on path
- [ ] Return 403 for unauthorized requests
- [ ] Log permission denials for debugging

### 1.3 Internal Trust in Monolith (Dev B)
- [ ] Create internal call detection mechanism in monolith
- [ ] Option: Check source IP (internal network) or header
- [ ] Update monolith auth middleware to skip JWT validation for internal calls
- [ ] Ensure external calls still require JWT validation

### 1.4 Integration Testing (Dev B)
- [ ] Test: Valid JWT passes through gateway
- [ ] Test: Invalid JWT rejected at gateway (401)
- [ ] Test: Missing permissions rejected (403)
- [ ] Test: Internal calls from gateway accepted by monolith
- [ ] Test: Direct external calls to monolith still require JWT

**Exit Criteria**: Auth happens once at gateway. Monolith doesn't validate JWT for internal calls.

---

## Phase 2: Organization Domain

**Goal**: Tokens, modules, org context owned by gateway
**Duration**: 1-2 sprints | **Devs**: 2 (parallel workstreams)

### 2.1 Organizations Table Migration (Dev A)
- [ ] Create `organizations` model in global-service
- [ ] Write Alembic migration to create table in `global_schema`
- [ ] Write data migration script to copy from monolith
- [ ] Verify data integrity after migration

### 2.2 Token Balance Endpoints (Dev A)
- [ ] Port `TokenManager` service from monolith
- [ ] Implement `GET /organizations/{id}/tokens` - get balance
- [ ] Implement `POST /organizations/{id}/tokens` - add tokens
- [ ] Wire up token consumption logic

### 2.3 Token Transactions Migration (Dev A)
- [ ] Create `token_transactions` model in global-service
- [ ] Write Alembic migration for `global_schema`
- [ ] Implement `GET /organizations/{id}/tokens/history` - transaction history
- [ ] Port transaction logging logic

### 2.4 Org Context Endpoints (Dev B)
- [ ] Implement `GET /current` - current organization context
- [ ] Implement `GET /activities` - recent organization activities
- [ ] Port Keycloak admin service for activities
- [ ] Port activity aggregation logic from monolith

### 2.5 Modules Table Migration (Dev B)
- [ ] Create `organization_modules` model in global-service
- [ ] Write Alembic migration for `global_schema`
- [ ] Write data migration script

### 2.6 Module Enablement Endpoints (Dev B)
- [ ] Implement `GET /organizations/{id}/modules` - list modules
- [ ] Implement `PUT /organizations/{id}/modules` - update modules
- [ ] Implement `PUT /organizations/{id}/modules/{module}/toggle`

### 2.7 Cleanup Monolith (Both)
- [ ] Remove token endpoints from monolith router
- [ ] Remove org context endpoints from monolith router
- [ ] Remove module endpoints from monolith router
- [ ] Update gateway proxy to exclude migrated routes
- [ ] Delete unused code from monolith

**Tests (Phase 2)**:
- [ ] Token balance CRUD works
- [ ] Token consumption deducts correctly
- [ ] Transaction history returns correct data
- [ ] Org context returns current user's org
- [ ] Module toggle works

**Exit Criteria**: Organization domain fully owned by gateway.

---

## Phase 3: Folders Domain

**Goal**: Folder system owned by gateway with generic item references
**Duration**: 1-2 sprints | **Devs**: 2 (parallel workstreams)

### 3.1 Folders Table Migration (Dev A)
- [ ] Create `folders` model in global-service
- [ ] Write Alembic migration for `global_schema`
- [ ] Write data migration script
- [ ] Verify folder data integrity

### 3.2 Folders CRUD Endpoints (Dev A)
- [ ] Port folder service from monolith
- [ ] Implement `GET /folders` - list user's folders
- [ ] Implement `POST /folders` - create folder
- [ ] Implement `GET /folders/{id}` - get folder
- [ ] Implement `PUT /folders/{id}` - update folder
- [ ] Implement `DELETE /folders/{id}` - delete folder
- [ ] Port folder access control logic

### 3.3 Folder Shares Migration (Dev A)
- [ ] Create `folder_shares` model in global-service
- [ ] Write Alembic migration for `global_schema`
- [ ] Implement `GET /folders/{id}/shares` - list shares
- [ ] Implement `POST /folders/{id}/shares` - add share
- [ ] Implement `DELETE /folders/{id}/shares/{user_id}` - remove share

### 3.4 Folder Items Migration (Dev B)
- [ ] Create `folder_items` model with `item_id` (string) + `item_type` (enum)
- [ ] Update `item_type` enum to support future modules (screen, target, explore)
- [ ] Write Alembic migration for `global_schema`
- [ ] Write data migration script

### 3.5 Folder Items Endpoints (Dev B)
- [ ] Implement `GET /folders/{id}/items` - list items
- [ ] Implement `POST /folders/{id}/items` - add item `{id, type}`
- [ ] Implement `DELETE /folders/{id}/items/{item_id}` - remove item

### 3.6 Favorites Migration (Dev B)
- [ ] Create `user_folder_favorites` model in global-service
- [ ] Write Alembic migration for `global_schema`
- [ ] Implement `GET /folders/favorites` - list favorites
- [ ] Implement `POST /folders/{id}/favorite` - add favorite
- [ ] Implement `DELETE /folders/{id}/favorite` - remove favorite

### 3.7 Cleanup Monolith (Both)
- [ ] Remove folder endpoints from monolith router
- [ ] Update gateway proxy to exclude folder routes
- [ ] Delete unused folder code from monolith

**Tests (Phase 3)**:
- [ ] Folder CRUD works
- [ ] Folder sharing works
- [ ] Folder items with generic type works
- [ ] Favorites work
- [ ] Access control prevents unauthorized access

**Exit Criteria**: Folders domain fully owned by gateway.

---

## Phase 4: Users Domain

**Goal**: User management owned by gateway
**Duration**: 1-2 sprints | **Devs**: 2 (parallel workstreams)

### 4.1 Global User Admin Endpoints (Dev A)
- [ ] Port Keycloak admin service from monolith
- [ ] Implement `GET /users` - list all users (admin)
- [ ] Implement `PUT /users/{id}/organization` - assign org
- [ ] Implement `PUT /users/{id}/permissions` - update permissions
- [ ] Implement `PUT /users/{id}/disable` - disable user
- [ ] Implement `POST /users/{id}/reset-password` - reset password

### 4.2 Team Management Endpoints (Dev A)
- [ ] Implement `GET /team` - list team members
- [ ] Implement `POST /team` - invite team member
- [ ] Implement `PUT /team/{id}` - update team member
- [ ] Implement `DELETE /team/{id}` - remove from team
- [ ] Port team management logic from monolith

### 4.3 User Import Endpoint (Dev A)
- [ ] Implement `POST /users/import` - bulk import users
- [ ] Port import logic from monolith

### 4.4 Account Sessions Endpoints (Dev B)
- [ ] Implement `GET /users/me/sessions` - list sessions
- [ ] Implement `DELETE /users/me/sessions/{id}` - revoke session
- [ ] Implement `DELETE /users/me/sessions` - revoke all sessions
- [ ] Port session logic from monolith

### 4.5 Account Events Endpoints (Dev B)
- [ ] Implement `GET /users/me/events` - activity events
- [ ] Port events logic from monolith

### 4.6 User Preferences Migration (Dev B)
- [ ] Create `user_preferences` model in global-service
- [ ] Write Alembic migration for `global_schema`
- [ ] Implement `GET /ai-preferences` - get preferences
- [ ] Implement `PUT /ai-preferences` - update preferences
- [ ] Write data migration script

### 4.7 Cleanup Monolith (Both)
- [ ] Remove user admin endpoints from monolith router
- [ ] Remove team endpoints from monolith router
- [ ] Remove account endpoints from monolith router
- [ ] Remove preferences endpoints from monolith router
- [ ] Update gateway proxy to exclude migrated routes
- [ ] Delete unused code from monolith

**Tests (Phase 4)**:
- [ ] User admin CRUD works
- [ ] Team management works
- [ ] Session management works
- [ ] Preferences CRUD works
- [ ] Permission checks enforced

**Exit Criteria**: Users domain fully owned by gateway.

---

## Phase 5: Screen Finalization

**Goal**: Monolith becomes Screen Service, clean separation complete
**Duration**: 1 sprint | **Devs**: 2

### 5.1 Screen Schema Migration (Dev A)
- [ ] Write Alembic migration to move company tables to `screen_schema`
- [ ] Move `companies`, all `company_*` child tables
- [ ] Move `tasks`, `task_dependencies`
- [ ] Move `workflow_configs`
- [ ] Move `chapse_conversation_context`
- [ ] Write data migration script
- [ ] Verify data integrity after migration

### 5.2 Remove Global Code from Monolith (Dev A)
- [ ] Delete organization-related code
- [ ] Delete folder-related code
- [ ] Delete user management code
- [ ] Delete preferences code
- [ ] Clean up unused imports and dependencies
- [ ] Verify only screen-related code remains

### 5.3 Rename to Screen Service (Dev B)
- [ ] Rename `back/` directory to `screen-service/`
- [ ] Update `pyproject.toml` project name
- [ ] Update `Dockerfile` and `docker-compose.yml`
- [ ] Update CI/CD pipeline references
- [ ] Update Kubernetes manifests (if applicable)
- [ ] Update environment variable names

### 5.4 Update Gateway Routing (Dev B)
- [ ] Configure gateway to route `/api/companies/*` to screen-service
- [ ] Configure gateway to route `/api/tasks/*` to screen-service
- [ ] Configure gateway to route `/api/admin/workflows` to screen-service
- [ ] Configure gateway to route `/webhooks/*` to screen-service
- [ ] Test all screen endpoints through gateway

### 5.5 End-to-End Testing (Both)
- [ ] Run full integration test suite
- [ ] Test company CRUD through gateway
- [ ] Test task management through gateway
- [ ] Test workflow configuration through gateway
- [ ] Test webhook callbacks
- [ ] Test SSE task events through gateway
- [ ] Performance testing (response times)

**Tests (Phase 5)**:
- [ ] All company endpoints work
- [ ] All task endpoints work
- [ ] SSE streaming works through gateway
- [ ] Webhook callbacks reach screen service
- [ ] End-to-end user flows work

**Exit Criteria**: Clean separation - Gateway owns global, Screen Service owns screen.

---

## Phase 6: gRPC Migration

**Goal**: Add gRPC protocol support, target 100% coverage
**Duration**: 2 sprints | **Devs**: 4 (highly parallelizable)

### Sprint 1: Infrastructure

#### 6.1 Protocol Registry (Dev A)
- [ ] Design protocol registry data structure
- [ ] Implement registry loading from config
- [ ] Create route-to-protocol mapping
- [ ] Implement protocol selection logic in gateway
- [ ] Add ability to update registry without restart (optional)

#### 6.2 gRPC Client in Gateway (Dev A)
- [ ] Add gRPC dependencies (`grpcio`, `grpcio-tools`)
- [ ] Generate Python client stubs from proto files
- [ ] Create gRPC client wrapper with error handling
- [ ] Implement connection pooling for gRPC
- [ ] Add gRPC client health checks

#### 6.3 gRPC Server Setup (Dev B)
- [ ] Add gRPC dependencies to screen-service
- [ ] Create gRPC server configuration
- [ ] Set up gRPC server on port 50051
- [ ] Implement gRPC health check service
- [ ] Configure TLS for gRPC (if needed)

#### 6.4 Proto Definitions (Dev B)
- [ ] Create `protos/` directory structure
- [ ] Define `company.proto` - company service messages
- [ ] Define `task.proto` - task service messages
- [ ] Define `workflow.proto` - workflow service messages
- [ ] Define `common.proto` - shared messages (pagination, errors)
- [ ] Set up proto compilation in build process

### Sprint 2: Route Migration

#### 6.5 Companies gRPC Service (Dev C)
- [ ] Implement `CompanyService` gRPC server
- [ ] `ListCompanies` - paginated company list
- [ ] `GetCompany` - get single company
- [ ] `CreateCompany` - create company
- [ ] `UpdateCompany` - update company
- [ ] `DeleteCompany` - soft delete company
- [ ] `SearchCompanies` - search companies
- [ ] Update protocol registry to use gRPC for company routes

#### 6.6 Tasks gRPC Service (Dev C)
- [ ] Implement `TaskService` gRPC server
- [ ] `ListTasks` - list tasks for company
- [ ] `GetTask` - get single task
- [ ] `RestartTask` - restart failed task
- [ ] `UpdateTaskTokens` - update token usage
- [ ] Handle SSE streaming (gRPC server streaming or keep REST)
- [ ] Update protocol registry for task routes

#### 6.7 Workflows gRPC Service (Dev D)
- [ ] Implement `WorkflowService` gRPC server
- [ ] `ListWorkflows` - list workflow configs
- [ ] `UpdateWorkflow` - update workflow config
- [ ] Update protocol registry for workflow routes

#### 6.8 Remaining Endpoints (Dev D)
- [ ] Implement gRPC for chatbot endpoint (or keep REST if complex)
- [ ] Implement gRPC for cost-analysis endpoint
- [ ] Keep webhook callback as REST (external caller)
- [ ] Update protocol registry for remaining routes

#### 6.9 Final Verification (All)
- [ ] Verify 100% gRPC coverage (except webhooks)
- [ ] Performance comparison: REST vs gRPC
- [ ] Load testing with gRPC
- [ ] Documentation update

**Tests (Phase 6)**:
- [ ] All company endpoints work via gRPC
- [ ] All task endpoints work via gRPC
- [ ] Protocol registry correctly selects gRPC
- [ ] Fallback to REST works when needed
- [ ] Performance improvement measured

**Exit Criteria**: All gateway → screen communication uses gRPC.

---

## Summary

| Phase | Focus | Duration | Devs | Parallel Opportunity |
|-------|-------|----------|------|---------------------|
| 0 | Proxy Shell | 1 sprint | 2 | A (code) + B (infra) |
| 1 | Auth Layer | 1 sprint | 2 | A (gateway) + B (monolith) |
| 2 | Organization | 1-2 sprints | 2 | A (tokens) + B (org/modules) |
| 3 | Folders | 1-2 sprints | 2 | A (folders/shares) + B (items/favorites) |
| 4 | Users | 1-2 sprints | 2 | A (admin/team) + B (self-service) |
| 5 | Screen Final | 1 sprint | 2 | A (cleanup) + B (rename/routing) |
| 6 | gRPC | 2 sprints | 4 | Highly parallelizable |

**Total**: ~8-9 sprints with 4 developers

---

## Key Technical Decisions

1. **REST First, gRPC Later**: Focus on architecture correctness before optimization
2. **Schema Separation Upfront**: Create both schemas in Phase 0
3. **Strangler Fig**: Monolith "sheds" features until only Screen remains
4. **Internal Trust Model**: Screen Service trusts calls from Global Service
5. **No Circuit Breakers**: Simple error propagation initially
6. **Generic Folder Items**: `{id, type}` design for future modules
