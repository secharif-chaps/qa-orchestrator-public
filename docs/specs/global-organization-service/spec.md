# Specification: Global Organization Service

## Prerequisites

**COMPLETED**: Both prerequisite specs have been completed:

### ✅ `private-folders-sharing` (Completed 2025-12-17)
- Simplified permission system (`organization.read/write`, `screen.create`, `target.create`)
- Private folders with user-level sharing (`folder_shares` table)
- Owner-based access control (not organization-wide visibility)
- `is_orphaned` field for folder ownership handling
- Access control matrix: Owner (full), Writer (add items), Reader (view only)

### ✅ `global-token-system` (Completed 2025-12-17)
- Global token balance per organization (replaces per-module tokens)
- `organizations` table with `token_balance` field
- `token_transactions` table for audit trail
- Simple immediate consume pattern (not reserve-confirm)
- Removed `token_count` from `organization_modules`

**Impact on this spec:**
- Phase 2 (Tokens): Migrate the existing simpler implementation, not build reserve-confirm
- Phase 3 (Folders): Migrate the updated folder model WITH sharing support

## Goal

Create a dedicated microservice to centralize organization-scoped resources (module configuration, unified token management, private folders with sharing) and enable a multi-module architecture where Screen, Target, and future services share common functionality. The service exposes both REST and gRPC interfaces, allowing module services to start with REST for rapid iteration and migrate to gRPC for performance optimization.

## User Stories

- As a platform administrator, I want a unified token pool per organization so that tokens can be shared across all modules (Screen, Target, Explore) instead of being siloed per module.
- As a module service developer, I want to call a central service via REST or gRPC to check module enablement and manage tokens so that I can start with REST for rapid development and migrate to gRPC later for performance.

## Specific Requirements

**New FastAPI + gRPC Service Skeleton**
- Create a new Python FastAPI service named `global-service` with both REST and gRPC interfaces
- **Dual API approach**: All service-to-service operations available via both REST and gRPC
  - REST allows module services to integrate quickly with familiar patterns
  - gRPC provides better performance for high-frequency calls (migrate when ready)
- REST endpoints: Standard FastAPI routes with Pydantic request/response models
- gRPC endpoints: Mirror REST functionality with protobuf definitions
- Use grpcio and grpcio-tools for Python gRPC implementation
- Follow existing backend patterns from `mint-server/` (service layer, Pydantic schemas, SQLAlchemy models)
- Separate PostgreSQL database (`global_db`) in the same cluster as `mint_db`
- Internal service endpoints (token operations, module checks) support both protocols

**Organization Context Endpoint (Phase 1)**
- REST `GET /api/organization/context` returns enabled modules, organization settings for frontend startup
- Service-to-service endpoints (both REST and gRPC):
  - REST `GET /api/internal/organizations/{org_id}/context` / gRPC `GetOrganizationContext(organization_id)`
  - REST `GET /api/internal/organizations/{org_id}/modules/{module}/enabled` / gRPC `IsModuleEnabled(organization_id, module_name)`
- Organization data (name, ID) comes from JWT token, not stored in global DB
- Module enablement stored in `organization_modules` table (organization_id, module_name, enabled)

**Unified Token System (Phase 2) - Migrate Existing Implementation**

*Note: The `global-token-system` spec has already implemented a simpler token system in `mint-server`. Phase 2 migrates this existing implementation to the global service.*

- Migrate `organizations` table with `token_balance` field from `mint_db` to `global_db`
- Migrate `token_transactions` table for audit trail
- Single token balance per organization shared across all modules
- Simple immediate consume pattern (not reserve-confirm)
- Token cost: `TOKENS_PER_COMPANY = 35` (hardcoded constant, configurable costs deferred)

**Token Endpoints (Phase 2)**
- Service-to-service endpoints (both REST and gRPC):
  - REST `GET /api/internal/organizations/{org_id}/tokens/balance` / gRPC `GetTokenBalance(organization_id)` → returns balance
  - REST `POST /api/internal/tokens/consume` / gRPC `ConsumeTokens(organization_id, amount, reference_type, reference_id)` → deducts tokens
  - REST `POST /api/internal/organizations/{org_id}/tokens/add` / gRPC `AddTokens(organization_id, amount)` → admin adds tokens
- Database-level row locking (`SELECT FOR UPDATE`) prevents race conditions
- No reservation system - immediate consume with transaction logging

**Token Transaction Audit Log (Phase 2)**
- `token_transactions` table records all token operations (add, consume, adjustment)
- Fields: organization_id, amount, balance_after, transaction_type, reference_type, reference_id, created_at, created_by
- Critical for billing disputes and debugging
- REST `GET /api/organization/tokens/history` for admin viewing

**Private Folders Migration (Phase 3)**
- **PREREQUISITE**: `private-folders-sharing` spec ✅ COMPLETED
- Extract tables from `mint_db` to `global_db`:
  - `folders` (with `owner_id`, `is_orphaned` fields)
  - `folder_items` (polymorphic item references)
  - `folder_shares` (user-level sharing with reader/writer roles)
  - `user_folder_favorites` (user-scoped favorites)
- Folders are PRIVATE by default (owner + shared users only, NOT organization-wide)
- Access control: Owner has full control, Writers can add items, Readers can only view
- Flat structure (no hierarchy) with polymorphic item references
- `item_type` supports cross-module items: `company` (Screen), `watchfile` (Target), future types
- Frontend REST endpoints: Include sharing management endpoints
- Service-to-service endpoints (both REST and gRPC):
  - REST `POST /api/internal/folders` / gRPC `CreateFolder`
  - REST `GET /api/internal/users/{user_id}/folders` / gRPC `GetUserFolders` (owned + shared)
  - REST `GET /api/internal/folders/{id}` / gRPC `GetFolder` (with access check)
  - REST `GET /api/internal/folders/{id}/items` / gRPC `GetFolderItems`
  - REST `POST /api/internal/folders/{id}/items` / gRPC `AddItemToFolder` (owner/writer only)
  - REST `DELETE /api/internal/folders/{id}/items/{item_id}` / gRPC `RemoveItemFromFolder` (owner only)
  - REST `POST /api/internal/folders/{id}/shares` / gRPC `ShareFolder` (owner only)
  - REST `GET /api/internal/folders/{id}/access/{user_id}` / gRPC `CheckFolderAccess`

**Keycloak Client Credentials Authentication**
- Service-to-service calls (both REST and gRPC) use Keycloak client credentials flow (not user JWT)
- Each module service has dedicated Keycloak client (e.g., `screen-service`, `target-service`)
- Global service validates client credentials via Keycloak introspection
- REST internal endpoints: Bearer token in `Authorization` header
- gRPC endpoints: Token in metadata (standard gRPC auth pattern)
- Frontend calls use user JWT tokens (existing pattern)

**Kubernetes Deployment**
- Deploy as separate pod in `chapsmind` namespace alongside existing services
- ConfigMap for module registry (SCREEN, TARGET, EXPLORE with enabled/disabled defaults)
- Separate database connection string for `global_db`
- Health check endpoints for liveness/readiness probes
- Resource limits appropriate for low-latency token checks (sub-50ms target)

## Visual Design

No visual assets provided - this is a backend microservice specification.

## Existing Code to Leverage

**`/mint-server/app/models/folder.py` - Folder, FolderItem, and FolderShare models**
- **NOTE**: After `private-folders-sharing` spec is completed, this will include:
  - `FolderShare` model with user-level sharing (reader/writer roles)
  - `is_orphaned` field on Folder model
  - Access control based on ownership + sharing (not organization-wide)
- Existing flat folder structure with polymorphic `item_id` + `item_type` pattern
- organization_id, owner_id (Keycloak UUID), soft delete with `is_deleted` flag
- ARRAY type for tags, position field for item ordering
- Migrate ALL folder-related tables including `folder_shares`

**`/mint-server/app/services/folder.py` - FolderService implementation**
- **NOTE**: After `private-folders-sharing` spec, this will include sharing methods:
  - `share_folder()`, `unshare_folder()`, `get_folder_shares()`, `update_share_role()`
  - `has_folder_access()`, `get_user_folder_role()`, `is_folder_owner()`
  - `list_folders()` filtered by ownership + shares (not org-wide)
- Complete CRUD operations, item management, user favorites
- `_get_folder_items_summary()` pattern for joining item details
- Use as reference for gRPC service implementation
- Favorites functionality (add_favorite, remove_favorite, is_favorite)

**`/mint-server/app/models/organization.py` - Organization and OrganizationModule models**
- ✅ UPDATED by `global-token-system`: Now includes `Organization` model with `token_balance`
- ✅ UPDATED: `token_count` already removed from `OrganizationModule`
- ModuleName enum (SCREEN, TARGET, EXPLORE) to reuse
- `TokenTransaction` model for audit trail

**`/mint-server/app/services/token_manager.py` - Current token logic**
- ✅ UPDATED by `global-token-system`: Now uses global balance pattern
- `consume_tokens()` uses `with_for_update()` for row locking - reuse this pattern
- `InsufficientTokensException` with clear error messaging - adapt for gRPC errors
- Simple immediate consume pattern (not reserve-confirm)
- Transaction logging for all operations

**`/mint-server/app/core/organization.py` - JWT organization extraction**
- `extract_organization_from_token()` handles Keycloak organization claim formats
- `OrganizationContext` Pydantic model with organization_id, user_id, username
- `get_user_organization()` FastAPI dependency for REST endpoints
- Reuse for frontend-facing REST endpoints in global service

## Out of Scope

- Dynamic module registration at runtime (static config only for Phase 1)
- Module-specific user preferences (remain with module services)
- Rate limiting on global service endpoints (defer to future)
- Events/activity monitoring table (low priority, defer)
- Message queue or async patterns (synchronous REST/gRPC is sufficient)
- gRPC-Web for frontend (REST for frontend, REST/gRPC for services)
- Hierarchical folder structure (flat structure only)
- User preferences extraction to global service (defer)
- Target service implementation (only global service in this spec)
- "Share with everyone in org" folder option (individual sharing only, per private-folders-sharing spec)
- Automatic ownership transfer for orphaned folders (deferred to future)
- Reserve-confirm token pattern (using simpler immediate consume pattern from global-token-system)
- Configurable token costs table (using hardcoded TOKENS_PER_COMPANY=35 for now)
