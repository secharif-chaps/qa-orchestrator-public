# User Stories & Parallelization Plan

## Overview

This document breaks down the Global Service API Gateway migration into user stories optimized for a **4-developer team**. The plan follows the domain-focused Strangler Fig pattern, allowing parallel work streams while maintaining clear dependencies.

## Team Structure

- **4 Developers** working in parallel
- **2 parallel workstreams** per phase (typical)
- **Sprint duration**: 2 weeks (assumed)

---

## Phase 0: Proxy Shell

**Goal**: Gateway exists, frontend calls it, everything still works (pure pass-through)

**Duration**: 1 sprint | **Devs**: 2

| Story ID | Title | Dev | Description | Acceptance Criteria |
|----------|-------|-----|-------------|---------------------|
| US-0.1 | Global Service Project Setup | A | Create FastAPI project structure, Docker config, CI pipeline | Project builds, tests run, Docker image created |
| US-0.2 | Pure REST Proxy Implementation | A | Implement middleware that forwards all requests to monolith | All existing API tests pass through gateway |
| US-0.3 | Database Schema Creation | B | Create `global_schema` and `screen_schema` in PostgreSQL | Schemas exist, Alembic configured for both |
| US-0.4 | Infrastructure Deployment | B | Deploy gateway alongside monolith, update nginx/ingress routing | Frontend can call gateway URL |

**Exit Criteria**: Frontend calls `gateway.domain.com/api/*` and gets same responses as before.

**Parallelization**: Dev A (gateway code) and Dev B (infra/DB) work independently.

---

## Phase 1: Auth Layer

**Goal**: Gateway validates JWT and checks permissions, monolith trusts internal calls

**Duration**: 1 sprint | **Devs**: 2

| Story ID | Title | Dev | Description | Acceptance Criteria |
|----------|-------|-----|-------------|---------------------|
| US-1.1 | JWT Validation Middleware | A | Implement Keycloak JWT validation in gateway using `fastapi-keycloak` | Invalid tokens rejected at gateway |
| US-1.2 | Permission Checking Logic | A | Implement role-based permission checking in gateway | Unauthorized requests get 403 |
| US-1.3 | Internal Trust in Monolith | B | Update monolith to skip JWT validation for internal calls | Monolith accepts gateway calls without re-auth |
| US-1.4 | Internal Call Detection | B | Implement mechanism to identify internal calls (network-based or header) | Gateway calls distinguished from external |

**Exit Criteria**: Auth happens once at gateway. Monolith doesn't validate JWT for internal calls.

**Parallelization**: Dev A (gateway auth) and Dev B (monolith changes) work independently.

---

## Phase 2: Organization Domain

**Goal**: Token management, org context, and modules owned by gateway

**Duration**: 1-2 sprints | **Devs**: 2 (parallel workstreams)

### Workstream A: Token Management

| Story ID | Title | Dev | Description | Acceptance Criteria |
|----------|-------|-----|-------------|---------------------|
| US-2.1 | Organizations Table Migration | A | Migrate `organizations` table to `global_schema` | Table exists in global_schema |
| US-2.2 | Token Balance Endpoints | A | Implement `GET/POST /organizations/{id}/tokens` in gateway | Token balance read/write works |
| US-2.3 | Token Transactions Migration | A | Migrate `token_transactions` table, implement history endpoint | Transaction history accessible |

### Workstream B: Org Context & Modules

| Story ID | Title | Dev | Description | Acceptance Criteria |
|----------|-------|-----|-------------|---------------------|
| US-2.4 | Org Context Endpoints | B | Implement `/current` and `/activities` endpoints in gateway | Org context accessible via gateway |
| US-2.5 | Modules Table Migration | B | Migrate `organization_modules` table to `global_schema` | Table exists in global_schema |
| US-2.6 | Module Enablement Endpoints | B | Implement `GET/PUT /organizations/{id}/modules/*` in gateway | Module toggle works |

### Shared Cleanup

| Story ID | Title | Dev | Description | Acceptance Criteria |
|----------|-------|-----|-------------|---------------------|
| US-2.7 | Remove Migrated Code | Both | Remove org/token code from monolith, update proxy routing | Monolith no longer handles these routes |

**Exit Criteria**: Organization domain fully owned by gateway. Monolith no longer handles tokens/modules.

**Parallelization**: Dev A (tokens) and Dev B (org context/modules) work in parallel.

---

## Phase 3: Folders Domain

**Goal**: Folder system owned by gateway with generic item references

**Duration**: 1-2 sprints | **Devs**: 2 (parallel workstreams)

### Workstream A: Core Folders

| Story ID | Title | Dev | Description | Acceptance Criteria |
|----------|-------|-----|-------------|---------------------|
| US-3.1 | Folders Table Migration | A | Migrate `folders` table to `global_schema` | Table exists in global_schema |
| US-3.2 | Folders CRUD Endpoints | A | Implement `GET/POST/PUT/DELETE /folders/*` in gateway | Folder CRUD works |
| US-3.3 | Folder Shares Migration | A | Migrate `folder_shares` table, implement sharing endpoints | Folder sharing works |

### Workstream B: Items & Favorites

| Story ID | Title | Dev | Description | Acceptance Criteria |
|----------|-------|-----|-------------|---------------------|
| US-3.4 | Folder Items Migration | B | Migrate `folder_items` table with generic `{id, type}` design | Items table supports multiple types |
| US-3.5 | Folder Items Endpoints | B | Implement `/folders/{id}/items` endpoints | Add/remove items works |
| US-3.6 | Favorites Migration | B | Migrate `user_folder_favorites`, implement favorites endpoints | Favorites work |

### Shared Cleanup

| Story ID | Title | Dev | Description | Acceptance Criteria |
|----------|-------|-----|-------------|---------------------|
| US-3.7 | Remove Migrated Code | Both | Remove folder code from monolith | Monolith no longer handles folders |

**Exit Criteria**: Folders domain fully owned by gateway.

**Parallelization**: Dev A (core folders/sharing) and Dev B (items/favorites) work in parallel.

---

## Phase 4: Users Domain

**Goal**: User management owned by gateway

**Duration**: 1-2 sprints | **Devs**: 2 (parallel workstreams)

### Workstream A: Admin & Team

| Story ID | Title | Dev | Description | Acceptance Criteria |
|----------|-------|-----|-------------|---------------------|
| US-4.1 | Global User Admin Endpoints | A | Implement `/users/*` endpoints in gateway (list, create, update, permissions) | User admin works |
| US-4.2 | Team Management Endpoints | A | Implement `/team/*` endpoints in gateway | Team management works |
| US-4.3 | User Import Endpoint | A | Implement bulk user import endpoint | CSV import works |

### Workstream B: Self-Service & Preferences

| Story ID | Title | Dev | Description | Acceptance Criteria |
|----------|-------|-----|-------------|---------------------|
| US-4.4 | Account Sessions Endpoints | B | Implement `/users/me/sessions` in gateway | Session list/revoke works |
| US-4.5 | Account Events Endpoints | B | Implement `/users/me/events` in gateway | Activity events accessible |
| US-4.6 | User Preferences Migration | B | Migrate `user_preferences` table, implement endpoints | Preferences work |

### Shared Cleanup

| Story ID | Title | Dev | Description | Acceptance Criteria |
|----------|-------|-----|-------------|---------------------|
| US-4.7 | Remove Migrated Code | Both | Remove user code from monolith | Monolith no longer handles users |

**Exit Criteria**: Users domain fully owned by gateway.

**Parallelization**: Dev A (admin/team) and Dev B (self-service/preferences) work in parallel.

---

## Phase 5: Screen Finalization

**Goal**: Monolith becomes Screen Service, clean separation complete

**Duration**: 1 sprint | **Devs**: 2

| Story ID | Title | Dev | Description | Acceptance Criteria |
|----------|-------|-----|-------------|---------------------|
| US-5.1 | Screen Schema Migration | A | Migrate remaining tables to `screen_schema` | Companies, tasks, etc. in screen_schema |
| US-5.2 | Remove Global Code | A | Remove all global code remaining in monolith | Only screen code remains |
| US-5.3 | Rename to Screen Service | B | Rename `back/` → `screen-service/`, update Docker/CI | Service renamed everywhere |
| US-5.4 | Update Gateway Routing | B | Update gateway to route screen endpoints to screen service | Routing works correctly |
| US-5.5 | End-to-End Testing | Both | Full integration test of complete architecture | All features work |

**Exit Criteria**: Clean separation - Gateway owns global, Screen Service owns screen.

**Parallelization**: Dev A (code cleanup) and Dev B (infra/naming) work in parallel.

---

## Phase 6: gRPC Migration

**Goal**: Add gRPC protocol support, target 100% coverage

**Duration**: 2 sprints | **Devs**: 4 (highly parallelizable)

### Sprint 1: Infrastructure

| Story ID | Title | Dev | Description | Acceptance Criteria |
|----------|-------|-----|-------------|---------------------|
| US-6.1 | Protocol Registry | A | Implement protocol registry in gateway | Registry maps routes to protocols |
| US-6.2 | gRPC Client in Gateway | A | Add gRPC client capability to gateway | Gateway can call gRPC services |
| US-6.3 | gRPC Server Setup | B | Set up gRPC server in Screen Service | gRPC server running on :50051 |
| US-6.4 | Proto Definitions | B | Define .proto files for screen services | Proto files for all screen endpoints |

### Sprint 2: Route Migration

| Story ID | Title | Dev | Description | Acceptance Criteria |
|----------|-------|-----|-------------|---------------------|
| US-6.5 | Companies gRPC | C | Implement gRPC for company endpoints | Companies work over gRPC |
| US-6.6 | Tasks gRPC | C | Implement gRPC for task endpoints | Tasks work over gRPC |
| US-6.7 | Workflows gRPC | D | Implement gRPC for workflow endpoints | Workflows work over gRPC |
| US-6.8 | Remaining Endpoints | D | Implement gRPC for webhooks, chatbot, cost-analysis | All screen endpoints on gRPC |
| US-6.9 | Registry Update | All | Update protocol registry to use gRPC for migrated routes | 100% gRPC coverage |

**Exit Criteria**: All gateway → screen communication uses gRPC.

**Parallelization**: Highly parallelizable - each dev can work on different gRPC services.

---

## Sprint Planning Summary

```
Sprint 1:  Phase 0 (2 devs) + Phase 1 prep (2 devs reading code)
Sprint 2:  Phase 1 (2 devs) + Phase 2 prep
Sprint 3:  Phase 2 (2 devs parallel: tokens | org-context)
Sprint 4:  Phase 3 (2 devs parallel: folders | items)
Sprint 5:  Phase 4 (2 devs parallel: admin | self-service)
Sprint 6:  Phase 5 (2 devs)
Sprint 7:  Phase 6 - Sprint 1 (4 devs on gRPC infra)
Sprint 8:  Phase 6 - Sprint 2 (4 devs on gRPC routes)
```

**Total Duration**: ~8 sprints (16 weeks) with 4 developers

---

## Dependencies Graph

```
Phase 0 (Proxy Shell)
    │
    ▼
Phase 1 (Auth Layer)
    │
    ├──────────────────┬──────────────────┐
    ▼                  ▼                  ▼
Phase 2            Phase 3            Phase 4
(Organization)     (Folders)          (Users)
    │                  │                  │
    └──────────────────┴──────────────────┘
                       │
                       ▼
               Phase 5 (Screen Finalization)
                       │
                       ▼
               Phase 6 (gRPC Migration)
```

**Note**: Phases 2, 3, and 4 can run in parallel after Phase 1 is complete. This is the main parallelization opportunity for the team.

---

## Risk Mitigation

| Risk | Mitigation |
|------|------------|
| Database migration issues | Test migrations on staging first, have rollback scripts ready |
| Auth bypass vulnerabilities | Security review after Phase 1, penetration testing |
| Performance regression | Load testing after each phase |
| Feature parity issues | Comprehensive API tests, run against both old and new endpoints |
| gRPC learning curve | Allocate extra time in Phase 6, pair programming |

---

## Success Metrics

- [ ] Frontend works identically throughout migration (zero downtime)
- [ ] All existing API tests pass at each phase
- [ ] No auth/permission regressions
- [ ] Database queries stay performant (< 100ms p95)
- [ ] 100% gRPC coverage by end of Phase 6
