# Task Breakdown: Global Organization Service

## Prerequisites

**COMPLETED**: Both prerequisite specs have been completed:

### ✅ `private-folders-sharing` (Completed 2025-12-17)
- `folder_shares` table (user-level sharing with reader/writer roles)
- `is_orphaned` field on Folder model
- Access control based on ownership + sharing (not organization-wide)
- Simplified permissions: `organization.read/write`, `screen.create`, `target.create`

### ✅ `global-token-system` (Completed 2025-12-17)
- `organizations` table with `token_balance` field
- `token_transactions` table for audit trail
- Simple immediate consume pattern (not reserve-confirm)
- Removed `token_count` from `organization_modules`

## Overview

**Total Phases**: 3
**Total Task Groups**: 10 (reduced from 12 - simplified Phase 2)
**Estimated Duration**: Each phase is independently deployable

This specification creates a new microservice (`global-service`) to centralize organization-scoped resources with both REST and gRPC interfaces.

**Dependency Order**:
1. Phase 1: Can start immediately (service foundation)
2. Phase 2: Migrate existing token implementation from `mint-server`
3. Phase 3: Migrate folder model with sharing support

---

## Phase 1: Service Foundation & Organization Context

### Task Group 1: Project Skeleton & Infrastructure
**Dependencies:** None
**Complexity:** M

- [ ] 1.0 Complete project skeleton setup
  - [ ] 1.1 Create `global-service/` directory structure
    - Follow existing `mint-server/` patterns
    - Structure: `app/`, `app/api/`, `app/models/`, `app/services/`, `app/core/`, `alembic/`, `tests/`
    - Include `pyproject.toml` with dependencies (FastAPI, SQLAlchemy, grpcio, grpcio-tools, pydantic)
  - [ ] 1.2 Set up FastAPI application entry point
    - Create `app/main.py` with FastAPI app initialization
    - Include health check endpoints: `GET /health/live`, `GET /health/ready`
    - Configure CORS for frontend access
  - [ ] 1.3 Set up database configuration
    - Create `app/database.py` with SQLAlchemy engine setup for `global_db`
    - Configure connection pooling
    - Create `app/core/config.py` with environment settings (Pydantic BaseSettings)
  - [ ] 1.4 Set up Alembic migrations
    - Initialize Alembic with `alembic/` directory
    - Configure for `global_db` connection
    - Create initial migration for empty database
  - [ ] 1.5 Verify project skeleton runs locally
    - Test FastAPI app starts without errors
    - Test health endpoints respond correctly
    - Verify database connection established

**Acceptance Criteria:**
- FastAPI application starts and serves health endpoints
- Database connection configured and tested
- Alembic migrations directory ready
- Project follows `mint-server/` patterns

---

### Task Group 2: gRPC Infrastructure Setup
**Dependencies:** Task Group 1
**Complexity:** M

- [ ] 2.0 Complete gRPC infrastructure
  - [ ] 2.1 Create protobuf definitions directory structure
    - Create `protos/` directory at project root
    - Create `protos/organization.proto` with service definitions
    - Define `GetOrganizationContext` and `IsModuleEnabled` RPCs
  - [ ] 2.2 Implement protobuf generation script
    - Create `scripts/generate_protos.py` or Makefile target
    - Generate Python stubs to `app/grpc_generated/`
    - Add generated files to `.gitignore` or commit (per team preference)
  - [ ] 2.3 Create gRPC server setup
    - Create `app/grpc_server.py` with concurrent server setup
    - Configure reflection for debugging
    - Set up graceful shutdown handling
  - [ ] 2.4 Create dual-server runner
    - Update `app/main.py` to run both REST and gRPC servers
    - REST on port 8000, gRPC on port 50051 (configurable)
    - Ensure both servers can run concurrently
  - [ ] 2.5 Write 2-4 tests for gRPC server startup
    - Test gRPC server starts on correct port
    - Test reflection is available
    - Test graceful shutdown

**Acceptance Criteria:**
- Protobuf files compile successfully
- gRPC server starts alongside FastAPI
- Both servers accessible on different ports
- Tests pass for gRPC server lifecycle

---

### Task Group 3: Keycloak Authentication
**Dependencies:** Task Group 1
**Complexity:** M

- [ ] 3.0 Complete authentication setup
  - [ ] 3.1 Set up Keycloak configuration
    - Create `app/core/keycloak.py` with fastapi-keycloak setup
    - Configure for both user JWT and client credentials validation
    - Reuse patterns from `mint-server/app/core/keycloak.py`
  - [ ] 3.2 Implement client credentials validation
    - Create `app/core/client_auth.py` for service-to-service auth
    - Implement token introspection via Keycloak
    - Cache introspection results with TTL
  - [ ] 3.3 Create authentication dependencies
    - Create `get_current_user()` dependency for REST (user JWT)
    - Create `get_service_client()` dependency for REST (client credentials)
    - Create gRPC interceptors for both auth methods
  - [ ] 3.4 Implement organization context extraction
    - Create `app/core/organization.py` (copy from `mint-server`)
    - Adapt `extract_organization_from_token()` for global service
    - Create `OrganizationContext` Pydantic model
  - [ ] 3.5 Write 2-4 tests for authentication
    - Test user JWT validation
    - Test client credentials validation
    - Test organization extraction from token

**Acceptance Criteria:**
- User JWT tokens validated for frontend REST calls
- Client credentials validated for service-to-service calls
- Organization context extracted from tokens
- Both REST and gRPC endpoints can be protected

---

### Task Group 4: Organization Modules Data Layer
**Dependencies:** Task Groups 1, 3
**Complexity:** S

- [ ] 4.0 Complete organization modules data layer
  - [ ] 4.1 Write 2-4 tests for organization modules
    - Test module enablement CRUD operations
    - Test organization-module uniqueness constraint
    - Test default module creation
  - [ ] 4.2 Create OrganizationModule model
    - Create `app/models/organization.py`
    - Fields: `id`, `organization_id` (String), `module_name` (Enum), `enabled` (Boolean)
    - Reuse `ModuleName` enum from `mint-server` (SCREEN, TARGET, EXPLORE)
    - Add timestamps: `created_at`, `updated_at`
    - Add unique constraint on `(organization_id, module_name)`
  - [ ] 4.3 Create migration for organization_modules table
    - Create Alembic migration
    - Add index on `organization_id`
    - Include both upgrade and downgrade
  - [ ] 4.4 Create OrganizationModuleService
    - Create `app/services/organization.py`
    - Methods: `get_or_create_module()`, `get_all_modules()`, `is_module_enabled()`, `set_module_enabled()`
    - Follow patterns from `mint-server/app/services/token_manager.py`
  - [ ] 4.5 Ensure data layer tests pass
    - Run tests from 4.1
    - Verify migration applies successfully

**Acceptance Criteria:**
- OrganizationModule model created with correct schema
- Migration runs successfully up and down
- Service layer provides CRUD operations
- Tests pass for critical operations

---

### Task Group 5: Organization Context REST & gRPC Endpoints
**Dependencies:** Task Groups 3, 4
**Complexity:** M

- [ ] 5.0 Complete organization context endpoints
  - [ ] 5.1 Write 2-4 tests for REST endpoints
    - Test `GET /api/organization/context` returns enabled modules
    - Test authentication required
    - Test organization filtering
  - [ ] 5.2 Create frontend REST endpoint
    - Create `app/api/endpoints/organization.py`
    - `GET /api/organization/context` - returns enabled modules, settings
    - Requires user JWT authentication
    - Returns: `{ organization_id, modules: [{name, enabled}] }`
  - [ ] 5.3 Create internal REST endpoints
    - `GET /api/internal/organizations/{org_id}/context` - full context
    - `GET /api/internal/organizations/{org_id}/modules/{module}/enabled` - boolean check
    - Requires client credentials authentication
  - [ ] 5.4 Implement gRPC service
    - Create `app/services/grpc/organization_service.py`
    - Implement `GetOrganizationContext(organization_id)` RPC
    - Implement `IsModuleEnabled(organization_id, module_name)` RPC
    - Add gRPC auth interceptor
  - [ ] 5.5 Write 2-4 tests for gRPC endpoints
    - Test `GetOrganizationContext` returns correct data
    - Test `IsModuleEnabled` for enabled/disabled modules
    - Test authentication enforcement
  - [ ] 5.6 Ensure all organization context tests pass
    - Run REST endpoint tests
    - Run gRPC endpoint tests

**Acceptance Criteria:**
- Frontend can fetch organization context via REST
- Services can check module enablement via REST or gRPC
- All endpoints properly authenticated
- Response formats match spec requirements

---

### Task Group 6: Kubernetes Deployment (Phase 1)
**Dependencies:** Task Groups 1-5
**Complexity:** M

- [ ] 6.0 Complete Kubernetes deployment
  - [ ] 6.1 Create Dockerfile
    - Multi-stage build for smaller image
    - Include both REST and gRPC server startup
    - Follow existing `mint-server` Dockerfile patterns
  - [ ] 6.2 Create Kubernetes manifests
    - Deployment with appropriate resource limits
    - Service exposing both REST (8000) and gRPC (50051) ports
    - ConfigMap for module registry (SCREEN, TARGET, EXPLORE defaults)
  - [ ] 6.3 Create ConfigMap for configuration
    - Module defaults (enabled/disabled per module)
    - Database connection string for `global_db`
    - Keycloak configuration
  - [ ] 6.4 Set up health probes
    - Liveness probe: `GET /health/live`
    - Readiness probe: `GET /health/ready`
    - Configure appropriate timeouts
  - [ ] 6.5 Create database migration job
    - Kubernetes Job to run Alembic migrations
    - Run before deployment update
  - [ ] 6.6 Test deployment locally (minikube/kind) or staging
    - Verify pods start successfully
    - Test endpoints are accessible
    - Verify database connectivity

**Acceptance Criteria:**
- Docker image builds successfully
- Kubernetes manifests deploy without errors
- Health probes pass
- Service accessible via both REST and gRPC
- Resource limits set for sub-50ms latency target

---

## Phase 2: Token System Migration

*Note: The `global-token-system` spec has already implemented a simpler token system in `mint-server`. Phase 2 migrates this existing implementation to the global service.*

### Task Group 7: Token Data Layer Migration
**Dependencies:** Phase 1 complete
**Complexity:** M (reduced - migrating existing implementation)

- [ ] 7.0 Complete token data layer migration
  - [ ] 7.1 Write 3-4 tests for token models
    - Test Organization model with token_balance field
    - Test TokenTransaction model creation
    - Test transaction type and reference type enums
    - Test balance updates with transaction logging
  - [ ] 7.2 Create Organization model (copy from mint-server)
    - Create `app/models/organization.py`
    - Copy `Organization` model from `/back/app/models/organization.py`
    - Fields: `organization_id` (VARCHAR PK), `token_balance` (Integer default 0), `created_at`, `updated_at`
  - [ ] 7.3 Create TokenTransaction model (copy from mint-server)
    - Copy from `/back/app/models/organization.py`
    - Fields: `id`, `organization_id`, `amount`, `balance_after`, `transaction_type`, `reference_type`, `reference_id`, `created_at`, `created_by`
    - TransactionType enum: add, consume, adjustment
    - ReferenceType enum: company, csv_import, manual, system
  - [ ] 7.4 Create migrations for token tables
    - Migration for `organizations` table
    - Migration for `token_transactions` table
    - Include indexes on organization_id, created_at
  - [ ] 7.5 Create data migration script
    - Script to migrate token balances from `mint_db.organizations` to `global_db.organizations`
    - Script to migrate transaction history (optional - may start fresh)
  - [ ] 7.6 Ensure token data layer tests pass

**Acceptance Criteria:**
- Organization model created with token_balance
- TokenTransaction model created for audit trail
- Migrations run successfully
- Data migration script tested

---

### Task Group 8: Token Service Migration
**Dependencies:** Task Group 7
**Complexity:** M (reduced - migrating existing implementation)

- [ ] 8.0 Complete token service migration
  - [ ] 8.1 Write 4-6 tests for token operations
    - Test get_balance() returns correct balance
    - Test add_tokens() creates transaction and updates balance
    - Test consume_tokens() with sufficient balance
    - Test consume_tokens() with insufficient balance (raises error)
    - Test row locking prevents race conditions
  - [ ] 8.2 Create TokenService (adapt from mint-server)
    - Create `app/services/tokens.py`
    - Copy patterns from `/back/app/services/token_manager.py`
    - Method: `get_balance(org_id)` - returns balance
    - Method: `add_tokens(org_id, amount, user_id)` - admin adds tokens
    - Method: `consume_tokens(org_id, amount, reference_type, reference_id, user_id)` - deducts tokens
    - Method: `get_transaction_history(org_id, filters)` - paginated history
    - Use `SELECT FOR UPDATE` for row locking
  - [ ] 8.3 Implement InsufficientTokensError
    - Create custom exception in `app/core/exceptions.py`
    - HTTP 402 for REST, FAILED_PRECONDITION for gRPC
    - Include current_balance, required_amount in error response
  - [ ] 8.4 Define TOKENS_PER_COMPANY constant
    - Set to 35 (matching current implementation)
    - Place in `app/core/constants.py`
  - [ ] 8.5 Ensure token service tests pass

**Acceptance Criteria:**
- Token service methods work correctly
- Insufficient balance returns appropriate error
- Row locking prevents race conditions
- All operations create transaction records

---

### Task Group 9: Token REST & gRPC Endpoints
**Dependencies:** Task Group 8
**Complexity:** M

- [ ] 9.0 Complete token endpoints
  - [ ] 9.1 Write 2-4 tests for token REST endpoints
    - Test balance retrieval
    - Test consume tokens via REST
    - Test transaction history pagination
  - [ ] 9.2 Create frontend REST endpoints
    - `GET /api/organization/tokens/balance` - returns balance for UI
    - `GET /api/organization/tokens/history` - paginated transaction history
    - `POST /api/organization/tokens` - admin adds tokens (admin.organizations role)
    - Requires user JWT with appropriate permissions
  - [ ] 9.3 Create internal REST endpoints
    - `GET /api/internal/organizations/{org_id}/tokens/balance` - get balance
    - `POST /api/internal/tokens/consume` - body: `{organization_id, amount, reference_type, reference_id}`
    - `POST /api/internal/organizations/{org_id}/tokens/add` - admin adds tokens
    - Requires client credentials authentication
  - [ ] 9.4 Update protobuf definitions
    - Add `token.proto` with token service definitions
    - Define `GetTokenBalance`, `ConsumeTokens`, `AddTokens` RPCs
    - Regenerate Python stubs
  - [ ] 9.5 Implement gRPC token service
    - Create `app/services/grpc/token_service.py`
    - Mirror REST endpoint functionality
    - Return appropriate gRPC error codes
  - [ ] 9.6 Write 2-4 tests for gRPC token endpoints
    - Test consume via gRPC
    - Test balance retrieval via gRPC
    - Test add tokens via gRPC
  - [ ] 9.7 Ensure all token endpoint tests pass

**Acceptance Criteria:**
- Frontend can display token balance and history
- Services can consume tokens via REST or gRPC
- Admin can add tokens
- Error responses include helpful details

---

## Phase 3: Private Folders Migration

**✅ PREREQUISITE COMPLETED**: `private-folders-sharing` spec completed 2025-12-17

### Task Group 10: Folders Data Layer (with Sharing)
**Dependencies:** Phase 2 complete
**Note:** Folder model with sharing already implemented in mint-server
**Complexity:** L (increased due to sharing)

- [ ] 10.0 Complete folders data layer with sharing support
  - [ ] 10.1 Write 4-8 tests for folder models with sharing
    - Test folder CRUD operations
    - Test folder item polymorphic references
    - Test user favorites
    - Test soft delete
    - Test FolderShare creation with reader/writer roles
    - Test folder access control (owner vs shared user)
    - Test `is_orphaned` flag handling
    - Test `list_folders` returns owned + shared folders only
  - [ ] 10.2 Create Folder model
    - Create `app/models/folder.py`
    - Copy schema from `mint-server/app/models/folder.py` (POST private-folders-sharing)
    - Fields: `id`, `organization_id`, `owner_id`, `owner`, `name`, `color`, `icon`, `tags`, `is_deleted`, `is_orphaned`
    - Add timestamps
    - Add relationship: `shares = relationship("FolderShare", ...)`
  - [ ] 10.3 Create FolderItem model
    - Fields: `id`, `folder_id`, `item_id` (String), `item_type` (String), `position`, `owner`, `added_at`
    - `item_type` values: `company`, `watchfile`, future types
    - Foreign key to Folder with CASCADE delete
  - [ ] 10.4 Create FolderShare model
    - Fields: `id`, `folder_id`, `user_id` (String), `user_username` (String), `role` (Enum: reader/writer), `created_at`
    - Unique constraint on `(folder_id, user_id)`
    - Index on `user_id` for efficient lookup
    - Foreign key to Folder with CASCADE delete
  - [ ] 10.5 Create UserFolderFavorite model
    - Fields: `id`, `user_id` (Keycloak UUID), `folder_id`, `created_at`
    - Unique constraint on `(user_id, folder_id)`
  - [ ] 10.6 Create migrations for all folder tables
    - Include all indexes from updated schema
    - Add index on `organization_id` for Folder
    - Add index on `item_type` for FolderItem queries
    - Add index on `user_id` for FolderShare queries
  - [ ] 10.7 Create FolderService with sharing and access control
    - Create `app/services/folders.py`
    - CRUD: `create_folder()`, `get_folder()`, `update_folder()`, `delete_folder()` (soft delete)
    - Items: `add_item()`, `remove_item()`, `get_items()`, `reorder_items()`
    - Favorites: `add_favorite()`, `remove_favorite()`, `get_favorites()`
    - **Sharing**: `share_folder()`, `unshare_folder()`, `get_folder_shares()`, `update_share_role()`
    - **Access Control**: `has_folder_access()`, `get_user_folder_role()`, `is_folder_owner()`
    - **List**: `list_user_folders()` returns owned + shared folders (NOT org-wide)
    - Reference `mint-server/app/services/folder.py` (POST private-folders-sharing)
  - [ ] 10.8 Ensure folder data layer tests pass

**Acceptance Criteria:**
- Folder models match updated schema (with sharing)
- Migrations run successfully
- Service layer provides full CRUD + sharing
- Access control enforces owner/writer/reader roles
- `list_user_folders()` returns only owned + shared folders
- Polymorphic items supported
- Tests pass

---

### Task Group 11: Folders REST & gRPC Endpoints (with Sharing & Access Control)
**Dependencies:** Task Group 10
**Complexity:** L (increased due to sharing)

- [ ] 11.0 Complete folder endpoints with sharing and access control
  - [ ] 11.1 Write 4-8 tests for folder REST endpoints
    - Test folder CRUD via REST (owner only for write operations)
    - Test item management with role-based access
    - Test favorites
    - Test sharing endpoints (owner only)
    - Test access control (owner vs writer vs reader)
    - Test `GET /folders` returns owned + shared only (NOT org-wide)
    - Test non-owner receives 403 for write operations
    - Test writer can add items, cannot delete
  - [ ] 11.2 Create frontend REST endpoints with access control
    - `GET /api/folders` - list user's folders (owned + shared, NOT org-wide)
    - `POST /api/folders` - create folder (becomes owner)
    - `GET /api/folders/{id}` - get folder details (with access check)
    - `PUT /api/folders/{id}` - update folder (owner only)
    - `DELETE /api/folders/{id}` - soft delete folder (owner only)
    - `GET /api/folders/{id}/items` - list folder items (with access check)
    - `POST /api/folders/{id}/items` - add item (owner or writer + module permission)
    - `DELETE /api/folders/{id}/items/{item_id}` - remove item (owner only)
    - `POST /api/folders/{id}/favorite` - add to favorites
    - `DELETE /api/folders/{id}/favorite` - remove from favorites
    - `GET /api/folders/favorites` - list user's favorite folders
  - [ ] 11.3 Create sharing REST endpoints
    - `GET /api/folders/{id}/shares` - list shares (owner only)
    - `POST /api/folders/{id}/shares` - add share (owner only)
    - `PATCH /api/folders/{id}/shares/{user_id}` - update share role (owner only)
    - `DELETE /api/folders/{id}/shares/{user_id}` - remove share (owner only)
  - [ ] 11.4 Create internal REST endpoints with access control
    - `POST /api/internal/folders` - create folder
    - `GET /api/internal/users/{user_id}/folders` - list user's folders (owned + shared)
    - `GET /api/internal/folders/{id}` - get folder (with access check)
    - `GET /api/internal/folders/{id}/items` - get folder items
    - `POST /api/internal/folders/{id}/items` - add item (owner/writer)
    - `DELETE /api/internal/folders/{id}/items/{item_id}` - remove item (owner only)
    - `POST /api/internal/folders/{id}/shares` - add share (owner only)
    - `GET /api/internal/folders/{id}/access/{user_id}` - check user's access/role
    - Requires client credentials
  - [ ] 11.5 Update protobuf definitions
    - Add `folder.proto` with folder service definitions
    - Define: `CreateFolder`, `GetUserFolders`, `GetFolder`, `GetFolderItems`
    - Define: `AddItemToFolder`, `RemoveItemFromFolder` (with role checks)
    - Define: `ShareFolder`, `CheckFolderAccess`
    - Regenerate Python stubs
  - [ ] 11.6 Implement gRPC folder service with access control
    - Create `app/services/grpc/folder_service.py`
    - Mirror REST endpoint functionality
    - Enforce owner/writer/reader access rules
  - [ ] 11.7 Write 4-6 tests for gRPC folder endpoints
    - Test folder creation via gRPC
    - Test item management via gRPC
    - Test access control via gRPC
    - Test sharing via gRPC
    - Test `GetUserFolders` returns correct folders
  - [ ] 11.8 Ensure all folder endpoint tests pass

**Acceptance Criteria:**
- Frontend can manage folders via REST with proper access control
- Services can manage folder items via REST or gRPC
- Cross-module items supported (company, watchfile)
- Access control enforced: owner/writer/reader roles
- `GET /folders` returns ONLY owned + shared folders (not org-wide)
- Sharing endpoints restricted to owner only
- Favorites work per-user

---

## Execution Order Summary

**Phase 1 - Service Foundation (Groups 1-6)** ✅ Can start immediately
1. Task Group 1: Project Skeleton & Infrastructure
2. Task Group 2: gRPC Infrastructure Setup
3. Task Group 3: Keycloak Authentication
4. Task Group 4: Organization Modules Data Layer
5. Task Group 5: Organization Context REST & gRPC Endpoints
6. Task Group 6: Kubernetes Deployment (Phase 1)

**Phase 2 - Token System Migration (Groups 7-9)** ✅ Can start after Phase 1
7. Task Group 7: Token Data Layer Migration
8. Task Group 8: Token Service Migration
9. Task Group 9: Token REST & gRPC Endpoints

**Phase 3 - Private Folders Migration (Groups 10-11)** ✅ Prerequisites completed
10. Task Group 10: Folders Data Layer (with Sharing)
11. Task Group 11: Folders REST & gRPC Endpoints (with Sharing & Access Control)

---

## Cross-Phase Integration Notes

### ✅ Completed Dependencies

Both prerequisite specs have been completed (2025-12-17):

**`private-folders-sharing`** introduced:
- `folder_shares` table with reader/writer roles
- `is_orphaned` field on Folder model
- Access control based on ownership + sharing (not organization-wide)
- Simplified permissions: `organization.read/write`, `screen.create`, `target.create`

**`global-token-system`** introduced:
- `organizations` table with `token_balance` field
- `token_transactions` table for audit trail
- Simple immediate consume pattern (not reserve-confirm)
- Removed `token_count` from `organization_modules`

### Screen Service Updates (Not in Global Service Scope)
After each phase, the `mint-server` (Screen service) will need updates:
- **After Phase 1**: Update to call global service for module enablement checks
- **After Phase 2**: Refactor to call global service for token operations (migrate from local implementation)
- **After Phase 3**: Remove local folder tables, call global service for folder operations

### Database Migration Strategy
- Phase 1: New `global_db` database with organization_modules
- Phase 2: Migrate token tables from `mint_db` to `global_db`:
  - `organizations` table (with `token_balance`)
  - `token_transactions` table (audit trail)
  - Simple data migration script
- Phase 3: Migrate folder data from `mint_db` to `global_db`:
  - `folders` table (with `is_orphaned` field)
  - `folder_items` table
  - `folder_shares` table (reader/writer sharing)
  - `user_folder_favorites` table
  - Data migration script must preserve all sharing relationships

### Testing Approach
- Each task group starts with 2-8 focused tests (x.1 sub-task)
- Each task group ends with running only those tests
- Full integration testing at end of each phase
- Do NOT run entire test suite during individual task groups
