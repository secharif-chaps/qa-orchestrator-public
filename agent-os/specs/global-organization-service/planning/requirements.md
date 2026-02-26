# Spec Requirements: Global Organization Service

## Completed Prerequisites

**✅ `private-folders-sharing`** (Completed 2025-12-17)
- Private folders with user-level sharing (not organization-wide)
- `folder_shares` table with reader/writer roles
- Simplified permission system

**✅ `global-token-system`** (Completed 2025-12-17)
- Global token balance per organization (simpler immediate consume pattern)
- `organizations` table with `token_balance`
- `token_transactions` for audit trail

---

## Initial Description

**Feature: Global Organization Service (Microservices Architecture)**

The user wants to externalize common components from the current monolithic backend into a dedicated global service to prepare for a multi-module architecture.

**Current State:**
- 1 frontend (Vue.js), 1 backend (FastAPI Python), 1 DB, Keycloak with organizations

**Target Architecture:**
- Keycloak (organizations) - already exists
- **Global/Common Service (NEW)** - Python FastAPI - this is what we're specifying
- Screen Service (refactored from current backend) - Python FastAPI
- Target Service (new) - PHP Symphony
- Future: Explore, Stream, Discover services
- Unified Vue.js frontend calling different service APIs

**Global Service Responsibilities:**
1. Organization management
2. User management
3. Module enablement per organization (which modules are active)
4. Token management (shared pool across all modules)
5. Folders (shared resource - a folder can contain items from multiple modules)

**Key Behaviors:**
- Frontend calls global service on startup to get org data (enabled modules, token balance)
- Module services (Screen, Target) call global service to:
  - Verify module is enabled for org
  - Check/deduct tokens before actions
- Folders managed in global service but reference item IDs from module services

**Token System Change:**
- Current: per-module token counts
- New: shared token pool per org (e.g., org has 4000 tokens, screen creation = 35 tokens, watchfile = 500 tokens)

**Migration Phases (iterative):**
- Phase 1: Organization + module config
- Phase 2: Tokens (unified pool)
- Phase 3: Folders

---

## Requirements Discussion

### First Round Questions

**Q1:** I assume inter-service communication will be synchronous REST APIs (module services call global service endpoints directly), since all services are internal and latency is manageable. Is that correct, or would you prefer gRPC for performance, or a message queue (RabbitMQ) for async operations like token deductions?
**Answer:** gRPC for inter-service communication (synchronous for most actions).

**Q2:** I assume the global service will have its own dedicated PostgreSQL database, separate from the Screen service database (which keeps the current DB). Is that correct, or should they share a database with schema separation?
**Answer:** User requested recommendation. After analysis, confirmed **separate databases** - global service has own DB, Screen service keeps current DB. Same PostgreSQL cluster, different databases.

**Q3:** For service-to-service authentication, I assume module services will use service accounts with API keys or client credentials (not user JWT tokens) when calling the global service for operations like token deduction. Is that correct, or should they pass through the user's JWT?
**Answer:** Keycloak client credentials - each module has dedicated client + admin-cli with service account roles.

**Q4:** For token deduction timing, I assume a reserve-then-confirm pattern: module service reserves tokens before starting work, then confirms (finalizes deduction) on success or releases on failure. Is that correct, or should it be immediate deduction with refund on failure?
**Answer:** Confirmed reserve-then-confirm pattern. Critical: never go negative, display "balance minus reserved" to users.

**Q5:** I assume token costs will be configurable per action type (stored in DB, not hardcoded), so you can adjust "company creation = 35 tokens" without code changes. Is that correct?
**Answer:** Confirmed - configurable token costs in DB.

**Q6:** When an organization runs out of tokens mid-operation, I assume the operation should fail gracefully with a clear error (not partial completion). Is that correct?
**Answer:** Confirmed - fail gracefully with clear errors on token exhaustion.

**Q7:** If the global service is temporarily unavailable, I assume module services should fail fast (reject requests that need token/module checks) rather than cache stale data or queue requests. Is that correct, or do you want a fallback strategy?
**Answer:** Confirmed - fail fast when global service unavailable.

**Q8:** I assume this will be deployed as a separate Kubernetes deployment in the chapsmind namespace, alongside existing services. Is that correct?
**Answer:** Confirmed - Kubernetes deployment in chapsmind namespace.

**Q9:** Is there anything that should explicitly be excluded from Phase 1 (Organization + Module Config)?
**Answer:** Keep Phase 1 minimal - iterative phases confirmed.

---

### Existing Code to Reference

**Similar Features Identified:**

- **Folder Implementation**: `/mint-server/app/models/folder.py`, `/mint-server/app/services/folder.py`
  - Flat structure (not hierarchical)
  - Join table pattern: `folder_items` with `item_id` (String) + `item_type` (String)
  - Already supports polymorphic items ('company', 'contact', 'document')
  - Metadata: name, color, icon, tags[], is_deleted
  - User-scoped favorites via `user_folder_favorites` table

- **Organization Context**: `/mint-server/app/core/organization.py`
  - NO organization table in PostgreSQL - 100% in Keycloak
  - organization_id stored as String UUID references
  - Organization context extracted from JWT at runtime

- **Token System**: `/mint-server/app/models/organization.py`, `/mint-server/app/services/token_manager.py`
  - Tightly coupled: `OrganizationModule` table has both `enabled` AND `token_count`
  - Per-module tokens: Each module has separate token balance (NOT shared)
  - Immediate deduction: Consume -> rollback on failure (not reserve-confirm)
  - Hardcoded cost: 35 tokens per company creation
  - Uses `with_for_update()` for locking

---

### Follow-up Questions

**Follow-up 1:** What user preferences are currently stored? Should we extract only cross-module preferences to global service?
**Answer:** Yes - cross-module preferences (theme, language, notifications) go to global service. Module-specific preferences stay with module services.

**Follow-up 2:** Should folders be organization-scoped, user-scoped, or both?
**Answer:** ~~All organization-scoped (nothing user-specific). Folders are shared within the organization.~~
**UPDATE (2025-12-17):** This has changed. The `private-folders-sharing` spec implemented **private folders** with user-level sharing. Folders are owned by users and explicitly shared (not organization-wide).

**Follow-up 3:** For the reserve-then-confirm pattern, what should happen to abandoned reservations?
**Answer:** ~~TTL-based expiry (reservations auto-release after timeout). Admin ability to manually release stuck reservations.~~
**UPDATE (2025-12-17):** The `global-token-system` spec implemented a **simpler immediate consume pattern** instead of reserve-confirm. No reservation system needed. This simplification will be migrated to the global service.

**Follow-up 4:** When a new module service comes online, how should it register with the global service?
**Answer:** Static configuration for Phase 1 (global service knows about all modules via config/env). Dynamic registration as future enhancement.

**Follow-up 5:** Should the global service maintain an audit log?
**Answer:** Yes for tokens (critical for billing disputes). Plus events table for activity monitoring (not priority). Other audit logging deferred.

**Follow-up 6:** Should frontend also use gRPC (gRPC-Web) so global service can be gRPC-only?
**Answer:** Recommendation provided and accepted - **Hybrid approach**: REST for frontend, gRPC for service-to-service. Rationale: matches existing frontend patterns, simpler debugging, no Envoy proxy needed, gRPC benefits are for high-frequency service calls not infrequent frontend calls.

---

## Visual Assets

### Files Provided:
No visual assets provided.

### Visual Insights:
N/A

---

## Requirements Summary

### Functional Requirements

**Organization Management:**
- Retrieve organization context (enabled modules, settings) via REST endpoint
- Organization data remains in Keycloak (no duplication in global DB)
- Module enablement configuration per organization

**Token Management:**
- Unified token pool per organization (replaces per-module tokens)
- ~~Reserve-then-confirm pattern~~ **Simple immediate consume pattern** (per `global-token-system` implementation)
- Never allow negative balance
- Token transaction audit log
- Token cost: TOKENS_PER_COMPANY = 35 (configurable costs deferred)

**Folder Management (Phase 3):**
- ~~Organization-scoped folders (shared within org)~~ **Private folders with user-level sharing** (per `private-folders-sharing` implementation)
- Owner-based access control with reader/writer sharing
- Flat structure (no hierarchy)
- Polymorphic item references (item_id + item_type)
- Cross-module items (companies, watchfiles, etc.)
- Metadata: name, color, icon, tags

**User Preferences:**
- Cross-module preferences only (theme, language, notifications)
- Module-specific preferences remain with module services

**API Design:**
- REST endpoints for frontend (OpenAPI/FastAPI)
- gRPC endpoints for service-to-service communication
- Keycloak client credentials for service authentication

---

### Technical Architecture

**Service Communication:**
```
Frontend (Vue.js)
    |
    | REST/HTTP (OpenAPI)
    v
Global Service (FastAPI)
    |
    | gRPC (protobuf)
    v
Module Services (Screen, Target, etc.)
```

**Database Strategy:**
- Separate PostgreSQL database for global service
- Same PostgreSQL cluster, different database
- Screen service keeps current database
- Clear data ownership boundaries

**Authentication:**
- Frontend: User JWT tokens (Keycloak)
- Service-to-service: Keycloak client credentials (each module has dedicated client)

**Module Registration:**
- Static configuration (environment/config based)
- Global service knows all modules at startup

---

### Tables to Create in Global Service DB

**From Migration (mint_db → global_db):**
- `organizations` - with `token_balance` field (from `global-token-system`)
- `token_transactions` - audit log (from `global-token-system`)
- `folders` - folder definitions with `is_orphaned` field
- `folder_items` - polymorphic item references
- `folder_shares` - user-level sharing with reader/writer roles (from `private-folders-sharing`)
- `user_folder_favorites` - user-scoped favorites
- `organization_modules` - module enablement per org (refactored)
- `user_preferences` - cross-module user preferences

**Removed (no longer needed):**
- ~~`token_costs` - configurable cost per action type~~ (deferred - using constants)
- ~~`token_reservations` - active reservations with TTL~~ (not needed - using simpler pattern)
- ~~`events` - activity monitoring~~ (deferred)

---

### Scope Boundaries

**In Scope:**

*Phase 1: Organization + Module Configuration*
- New global service skeleton (FastAPI + gRPC)
- Separate PostgreSQL database setup
- REST endpoint: Get organization context (for frontend)
- gRPC endpoints: GetOrganizationContext, IsModuleEnabled
- Module enablement per organization (organization_modules table)
- Keycloak client credentials authentication
- Kubernetes deployment in chapsmind namespace
- Screen service updated to call global service for module checks

*Phase 2: Unified Token System (Simplified - migrate existing implementation)*
- Migrate tables: `organizations` (with token_balance), `token_transactions`
- Simple immediate consume pattern (not reserve-confirm)
- gRPC endpoints: GetTokenBalance, ConsumeTokens, AddTokens
- REST endpoints: Get token balance, history (for frontend)
- Token transaction audit log
- Migration of existing token implementation from mint_db
- Screen service refactored to call global service for token operations

*Phase 3: Private Folders (with sharing support)*
- Migrate tables: `folders`, `folder_items`, `folder_shares`, `user_folder_favorites`
- Private folders with owner-based access control
- User-level sharing with reader/writer roles
- gRPC endpoints: CreateFolder, AddItemToFolder, RemoveItemFromFolder, GetUserFolders, GetFolderItems, ShareFolder, CheckFolderAccess
- REST endpoints for frontend folder and sharing operations
- Cross-module item support (companies + watchfiles + future types)
- Screen service updated to call global service for folder operations
- Target service can use folders from launch

**Out of Scope:**
- Dynamic module registration (future enhancement)
- ~~User-scoped personal folders (all folders are org-scoped)~~ N/A - folders are now private with sharing
- Module-specific user preferences (stay with module services)
- Rate limiting (defer to later)
- Complex audit logging beyond tokens (events table is low priority)
- Message queue / async patterns (synchronous gRPC is sufficient)
- Reserve-confirm token pattern (using simpler immediate consume)
- Configurable token costs table (using hardcoded constants for now)

---

### Technical Considerations

**Performance:**
- Scale: 100s of organizations, ~50 users currently (gradual growth)
- Token checks must be low-latency (gRPC appropriate)
- Consider connection pooling for gRPC clients in module services

**Resilience:**
- Fail fast when global service unavailable
- No caching of stale module/token data
- TTL-based cleanup of abandoned reservations
- Admin tooling to release stuck reservations

**Migration Strategy:**
- Iterative phases to minimize risk
- Each phase is independently deployable
- Screen service maintains backward compatibility during transition
- Data migration scripts for each phase

**Deployment:**
- Kubernetes deployment in chapsmind namespace
- Separate service, separate database
- Environment-based configuration for module registry
- Health checks for service discovery

**Integration Points:**
- Keycloak: Organization data, client credentials
- Screen Service: Module checks, token operations, folder operations
- Target Service: Same integration pattern (PHP/Symphony with gRPC client)
- Frontend: REST API calls on startup and for user-facing operations
