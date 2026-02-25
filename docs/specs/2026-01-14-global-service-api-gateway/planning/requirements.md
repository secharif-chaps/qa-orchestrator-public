# Spec Requirements: Global Service API Gateway

## Initial Description

Transform the global-service into an API Gateway architecture where:

**Current State (v1):**
- Monolithic FastAPI backend (`back/`) with everything combined
- Frontend calls backend directly
- Global features (tokens, folders, organizations, user management) mixed with Screen module features (companies, tasks, Dify workflows)

**Target State (v2):**
- Frontend → Global Service (API Gateway) → Screen Service (internal)
- Global Service acts as single entry point handling:
  - JWT validation and permission checking
  - Request routing (REST and gRPC)
  - Shared services (tokens, folders, organizations, user management, preferences)
- Screen Service becomes internal-only, handling company screening features

**Key Decisions Made:**
1. Communication: Both REST and gRPC from day one
2. Database: Shared PostgreSQL with separate schemas (global_schema, screen_schema)
3. User Preferences: Move to Global Service
4. Folders: Global Service with generic item references {id, type} for future-proofing
5. Activities Feed: Global Service
6. Workflow Configuration: Screen Service (Dify-specific)

**Scope:**
- Detailed feature extraction mapping (which endpoints go where)
- Database schema migration plan
- API contract design for internal communication
- Big-bang migration approach (v2 replaces v1)

## Requirements Discussion

### Architecture Decisions

**Q1:** API Gateway Pattern - Should Global Service BE the API Gateway or use a separate component (Kong/NGINX)?
**Answer:** Global Service IS the API Gateway (as per ADR-0009). No separate Kong/NGINX component. Single entry point for all frontend requests.

**Q2:** Service Communication Protocols - What protocols should be used between services?
**Answer:**
- Frontend → Global Service: REST only
- Global Service → Screen Service: Both REST and gRPC available
- Services can choose protocol based on use case

**Q3:** Database Architecture - How should the database be structured?
**Answer:** Shared PostgreSQL with separate schemas: `global_schema` and `screen_schema`. Strict API-only communication between services (no cross-schema JOINs). Each service owns its schema exclusively.

**Q4:** Authentication Flow - How should JWT validation and user context propagation work?
**Answer:**
- Global Service validates JWT once (via Keycloak)
- Passes user context (org_id, user_id) to Screen Service via headers
- Screen Service trusts the call - NO re-validation
- Permissions NOT passed to Screen Service (it just handles screen-specific actions)

**Q5:** Migration Strategy - How should the migration from v1 to v2 be handled?
**Answer:** Strangler Fig Pattern:
- Build API Gateway incrementally
- Migrate routes one by one from monolith to new services
- Frontend can call existing backend while routes are being migrated
- Allows parallel feature development during migration

**Q6:** Celery Workers - Where do background workers belong?
**Answer:** Celery is ONLY for Screen Service. Handles Dify workflows and company data collection. Invisible to Global Service - no Celery integration needed.

**Q7:** Error Handling - What error handling patterns should be implemented?
**Answer:** Simply propagate errors for now. No caching or graceful degradation. Circuit breaker patterns deferred to future iterations.

**Q8:** What is explicitly out of scope?
**Answer:**
- Target Service integration
- Explore Service preparation
- Cross-module data sharing APIs

### Existing Code to Reference

**Similar Features Identified:**
- Feature: Current Monolith Backend - Path: `back/`
- Architecture Decision Records - Path: `agent-os/product/` (ADR-0009 referenced)
- Keycloak Integration - Existing authentication patterns in current backend

### Follow-up Questions

No follow-up questions were required.

## Visual Assets

### Files Provided:
No visual assets provided.

## Requirements Summary

### Functional Requirements

#### Global Service (API Gateway + Shared Services)

**Gateway Responsibilities:**
- JWT validation (Keycloak)
- Request routing to Screen Service
- Logging and observability

**Shared Services Endpoints:**
- `/current`, `/activities` - Organization context
- `/organizations/{id}/tokens/*` - Token management (balance, history, consumption)
- `/folders/*` - Folder CRUD, sharing, favorites, items (generic {id, type} references)
- `/team/*` - Team member management
- `/users/me/*` - Account management (sessions, events)
- `/users/*` - Global user admin
- `/organizations/*` - Organization admin CRUD
- `/organizations/{id}/modules/*` - Module enablement
- AI preferences (user_preferences)

**Database Tables (global_schema):**
- organizations (with token_balance)
- token_transactions
- folders
- folder_items (with generic item_id + item_type)
- folder_shares
- user_folder_favorites
- user_preferences
- organization_modules

#### Screen Service (Internal Only)

**Endpoints (routed through Global Service):**
- `/companies/*` - Company CRUD, search, CSV import
- `/tasks/*` - Task management, SSE events
- `/admin/workflows` - Workflow configuration (Dify)
- `/webhooks` - Dify callbacks
- `/{company_id}/chatbot` - AI chat

**Database Tables (screen_schema):**
- companies
- company_sections (profile, digital, timeline, products, jobs, csr, press, team)
- tasks
- workflow_configs
- chapse_conversation_context

**Infrastructure:**
- Celery workers for Dify workflow tasks
- RabbitMQ integration

### API Contract (Internal Communication)

Global Service → Screen Service calls include headers:
- `X-Organization-Id`: Keycloak org UUID
- `X-User-Id`: Keycloak user UUID
- `X-Username`: Username for display/logging

Screen Service trusts these headers when called from internal network.

### Reusability Opportunities
- Existing Keycloak authentication patterns from current backend
- FastAPI patterns and conventions already established
- Pydantic schema patterns for API contracts
- Alembic migration patterns for schema management

### Scope Boundaries

**In Scope:**
- Detailed feature extraction mapping (which endpoints go where)
- Database schema migration plan (global_schema, screen_schema)
- API contract design for internal communication
- Strangler Fig migration approach
- Global Service as API Gateway with shared services
- Screen Service as internal-only service

**Out of Scope:**
- Target Service integration
- Explore Service preparation
- Cross-module data sharing APIs
- Circuit breaker patterns
- Caching layer
- Graceful degradation

### Technical Considerations
- Global Service IS the API Gateway (no separate Kong/NGINX)
- Both REST and gRPC available for internal service communication
- Shared PostgreSQL with separate schemas (strict schema boundaries)
- JWT validated once at Global Service, user context passed via headers
- Screen Service trusts internal calls without re-validation
- Celery workers remain exclusive to Screen Service
- Strangler Fig pattern allows incremental migration
- Error propagation (no advanced error handling initially)
