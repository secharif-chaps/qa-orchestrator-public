# Task Breakdown: Global Token System

## Overview
Total Tasks: 6 Task Groups (~30 sub-tasks)

This feature simplifies the module-based token system to a single global token balance per organization with complete transaction history. The implementation requires careful migration of existing data and coordinated backend/frontend changes.

## Risk Assessment

**High Risk Areas:**
- Database migration summing existing tokens (data integrity)
- TokenManager refactor (core business logic change)
- Deprecating old endpoints while maintaining functionality

**Mitigation Strategy:**
- Separate schema migrations from data migrations
- Create migration first, test on dev data before production
- Keep backward compatibility during transition

---

## Task List

### Phase 1: Database Layer

#### Task Group 1: Schema Migrations
**Dependencies:** None

- [x] 1.0 Complete database schema migrations
  - [x] 1.1 Write 3-4 focused tests for new models
    - Test Organization model creation with defaults
    - Test TokenTransaction model with required fields
    - Test OrganizationModule without token_count field
    - Test transaction type and reference type enums
  - [x] 1.2 Create Organization model in `back/app/models/organization.py`
    - Fields: organization_id (VARCHAR PK), token_balance (Integer default 0), created_at, updated_at
    - Add index on organization_id
    - No foreign key to Keycloak (simple string reference)
  - [x] 1.3 Create TokenTransaction model in `back/app/models/organization.py`
    - Fields: id (Integer PK), organization_id (VARCHAR FK), amount (Integer), balance_after (Integer), transaction_type (Enum), reference_type (Enum), reference_id (VARCHAR nullable), created_at, created_by (VARCHAR)
    - Create TransactionType enum: add, consume, adjustment
    - Create ReferenceType enum: company, csv_import, manual, system
    - Add composite index on (organization_id, created_at)
  - [x] 1.4 Create Pydantic schemas for new models
    - OrganizationCreate, OrganizationRead, OrganizationUpdate schemas
    - TokenTransactionCreate, TokenTransactionRead schemas
    - TokenBalanceResponse schema: { balance: number, organization_id: string }
    - AddTokensRequest schema: { amount: int }
  - [x] 1.5 Create Alembic migration 1: Add new tables
    - Create organizations table with indexes
    - Create token_transactions table with indexes and FK
    - Do NOT modify organization_modules yet (separate migration)
  - [x] 1.6 Ensure schema migration tests pass
    - Run ONLY tests from 1.1
    - Verify migration applies successfully
    - Verify models can be queried

**Acceptance Criteria:**
- Organizations table created with correct schema
- TokenTransaction table created with FK and indexes
- Enums properly defined
- Migration is reversible
- Tests from 1.1 pass

---

### Phase 2: Data Migration

#### Task Group 2: Token Data Migration
**Dependencies:** Task Group 1

- [x] 2.0 Complete data migration from module tokens to global tokens
  - [x] 2.1 Write 2-3 focused tests for migration logic
    - Test summing tokens across multiple modules for one org
    - Test organization record creation with summed balance
    - Test initial transaction record creation
  - [x] 2.2 Create Alembic migration 2: Migrate token data
    - Query all unique organization_ids from organization_modules
    - For each org: SUM(token_count) regardless of enabled state
    - Insert organization record with summed balance
    - Create initial TokenTransaction record (type: adjustment, reference_type: system)
    - Include note: "Migration from module-based tokens"
  - [x] 2.3 Create Alembic migration 3: Clean up organization_modules
    - Drop token_count column from organization_modules
    - Delete records where module_name = 'stream' (if any exist)
    - Remove ModuleName.stream from enum (screen, target, explore only)
  - [x] 2.4 Update OrganizationModule model
    - Remove token_count column definition
    - Update ModuleName enum to only include: screen, target, explore
    - Keep: organization_id, module_name, enabled, created_at, updated_at
  - [x] 2.5 Test migration on development data
    - Run migrations in sequence
    - Verify token sums are correct
    - Verify transaction records exist
    - Verify organization_modules no longer has token_count

**Acceptance Criteria:**
- All existing module tokens summed correctly
- Organization records created for each org with tokens
- Initial transaction records created for audit trail
- token_count column removed from organization_modules
- 'stream' module records deleted
- Tests from 2.1 pass

---

### Phase 3: Backend API Layer

#### Task Group 3: TokenManager Refactor and New Endpoints
**Dependencies:** Task Group 2

- [x] 3.0 Complete backend API refactor
  - [x] 3.1 Write 4-6 focused tests for TokenManager and endpoints
    - Test get_balance() returns correct balance
    - Test add_tokens() creates transaction and updates balance
    - Test consume_tokens() with sufficient balance
    - Test consume_tokens() with insufficient balance (InsufficientTokensException)
    - Test consume_tokens() checks module enablement
    - Test get_transaction_history() with filters
  - [x] 3.2 Refactor TokenManager in `back/app/services/token_manager.py`
    - Replace module-based methods with global balance operations
    - Implement get_balance(org_id) -> int
    - Implement add_tokens(org_id, amount, user_id) -> Organization
    - Implement consume_tokens(org_id, amount, module_name, reference_type, reference_id, user_id) -> Organization
    - Implement get_transaction_history(org_id, filters) -> List[TokenTransaction]
    - Implement _ensure_organization_exists(org_id) for lazy initialization
    - Keep InsufficientTokensException with updated messaging
    - Use row-level locking (with_for_update) to prevent race conditions
    - All operations create transaction records atomically
    - REMOVE rollback_tokens method (no refund logic per requirements)
    - Define TOKENS_PER_COMPANY = 35 constant for company creation cost
  - [x] 3.3 Create new token endpoints in router
    - GET /organizations/{id}/tokens - Return TokenBalanceResponse
    - POST /organizations/{id}/tokens - Add tokens (admin.organizations role required)
    - GET /organizations/{id}/tokens/history - Paginated history with filters
    - Filters: transaction_type, reference_type, date_from, date_to, page, size
  - [x] 3.4 Remove old token endpoints and code
    - DELETE GET /{org_id}/modules/{module}/tokens endpoint entirely
    - DELETE POST /{org_id}/modules/{module}/tokens endpoint entirely
    - Remove token_count from ModuleUpdateRequest schema
    - Remove get_module_tokens(), add_tokens() (module-based) methods from TokenManager
    - Clean up any unused imports and helper functions
  - [x] 3.5 Update company creation endpoint
    - Modify company creation to use new TokenManager.consume_tokens()
    - Pass reference_type='company', reference_id=company_id
    - Keep 35 tokens per company cost unchanged
  - [x] 3.6 Ensure backend API tests pass
    - Run ONLY tests from 3.1
    - Verify all new endpoints work
    - Verify token consumption in company creation

**Acceptance Criteria:**
- TokenManager refactored with new global methods
- New endpoints return correct responses
- Old token endpoints removed entirely
- Company creation uses new token system
- Row-level locking prevents race conditions
- Tests from 3.1 pass

---

### Phase 4: Frontend Types and API Layer

#### Task Group 4: Frontend Types, API, Queries, and Mutations
**Dependencies:** Task Group 3

- [x] 4.0 Complete frontend data layer updates
  - [x] 4.1 Write 2-3 focused tests for queries and mutations
    - Test organizationBalanceQuery returns correct structure
    - Test tokenHistoryQuery with pagination
    - Test useAddGlobalTokens mutation
  - [x] 4.2 Update TypeScript types
    - Add TransactionType: 'add' | 'consume' | 'adjustment'
    - Add ReferenceType: 'company' | 'csv_import' | 'manual' | 'system'
    - Add TokenTransaction interface with all fields
    - Add TokenBalanceResponse interface: { balance: number, organization_id: string }
    - Add TokenHistoryFilters interface
    - Remove ModuleName 'stream' option
    - Remove token_count from ModuleConfig interface
  - [x] 4.3 Update API functions in `front/src/api/`
    - Create getOrganizationBalance(orgId) -> TokenBalanceResponse
    - Create addOrganizationTokens(orgId, amount) -> TokenBalanceResponse
    - Create getTokenHistory(orgId, filters) -> PaginatedResponse<TokenTransaction>
    - DELETE getModuleTokens(), addModuleTokens() functions entirely
    - Clean up unused imports
  - [x] 4.4 Update queries in `front/src/queries/tokens.ts`
    - Create ORGANIZATION_TOKEN_KEYS for cache management
    - Create organizationBalanceQuery using defineQueryOptions
    - Create tokenHistoryQuery with filter support
    - DELETE moduleTokensQuery and related query keys entirely
  - [x] 4.5 Update mutations in `front/src/mutations/tokens.ts`
    - Create useAddGlobalTokens mutation
    - Invalidate organizationBalanceQuery on success
    - Invalidate tokenHistoryQuery on success
    - DELETE useAddModuleTokens mutation entirely
    - Clean up unused imports and types
  - [x] 4.6 Ensure frontend data layer tests pass
    - Run ONLY tests from 4.1
    - Verify type safety across layer

**Acceptance Criteria:**
- All new types properly defined
- API functions work with new endpoints
- Queries properly configured with cache keys
- Mutations invalidate correct queries
- Tests from 4.1 pass

---

### Phase 5: Frontend Components

#### Task Group 5: UI Component Updates
**Dependencies:** Task Group 4

- [x] 5.0 Complete frontend UI updates
  - [x] 5.1 Write 3-4 focused tests for component behavior
    - Test OrganizationTokensManager displays global balance
    - Test quick-add buttons calculate correct amounts
    - Test ModuleTokenCard shows only toggle (no tokens)
    - Test TokenSidebar displays global balance
  - [x] 5.2 Create useGlobalTokens composable
    - DELETE useModuleTokens.ts entirely
    - Create new useGlobalTokens.ts composable
    - Expose: balance, isLoading, refreshTokenData
    - Keep cache invalidation patterns
    - Update useTokenValidation.ts to use global balance
  - [x] 5.3 Refactor OrganizationTokensManager.vue
    - Remove per-module token display and management
    - Display single global token balance prominently at top
    - Add quick-add buttons: 5, 10, 25, 50, 100 companies (x35 tokens each)
    - Add custom amount input field
    - Add link to token history page
    - Use Vuellar components (Button, Input)
  - [x] 5.4 Simplify ModuleTokenCard.vue
    - Remove token count display entirely
    - Keep only enabled/disabled toggle switch (Vuellar Switch)
    - Remove quick-add and custom amount controls
    - Focus component on module enablement only
  - [x] 5.5 Update TokenSidebar.vue
    - Display global balance using single query
    - Remove module token summing logic
    - Keep existing recent activity display
    - Update totalTokens computation to use new API
  - [x] 5.6 Create TokenHistoryPage.vue
    - Create new page at route /tokens/history (or /admin/tokens/history)
    - Table columns: Date, Type, Amount (+/-), Balance After, Reference, User
    - Use Vuellar Table component with custom cell templates
    - Add filters: transaction_type dropdown (Vuellar Select), date range picker (Vuellar DateRangePicker)
    - Add pagination with page size options: 10, 25, 50 (Vuellar Pagination)
    - Require organization.read permission
    - Add route meta for permissions
  - [x] 5.7 Update TokenCounter.vue
    - Reuse for global balance display
    - Remove module-specific props if any
    - Keep TOKENS_PER_COMPANY constant (35)
    - Keep loading and status indicator patterns
  - [x] 5.8 Ensure UI component tests pass
    - Run ONLY tests from 5.1
    - Verify components render correctly

**Acceptance Criteria:**
- Global token balance displayed prominently
- Quick-add buttons work correctly
- Module cards show only enablement toggle
- Token history page accessible and functional
- All components use Vuellar where applicable
- Tests from 5.1 pass

---

### Phase 6: Integration Testing

#### Task Group 6: Test Review and Gap Analysis
**Dependencies:** Task Groups 1-5

- [x] 6.0 Review existing tests and fill critical gaps only
  - [x] 6.1 Review tests from Task Groups 1-5
    - Review 3-4 tests from database layer (Task 1.1)
    - Review 2-3 tests from data migration (Task 2.1)
    - Review 4-6 tests from backend API (Task 3.1)
    - Review 2-3 tests from frontend data layer (Task 4.1)
    - Review 3-4 tests from UI components (Task 5.1)
    - Total existing tests: approximately 14-20 tests
  - [x] 6.2 Analyze test coverage gaps for this feature
    - Identify critical end-to-end workflows lacking coverage
    - Focus on: token consumption during company creation, admin token management, history viewing
    - Do NOT assess entire application test coverage
  - [x] 6.3 Write up to 8 additional strategic tests if needed
    - End-to-end: Admin adds tokens, user creates company, balance updates correctly
    - End-to-end: Token history shows all transactions in correct order
    - Integration: Race condition prevention with concurrent token consumption
    - Edge case: Insufficient tokens blocks company creation
    - Permission: Non-admin cannot add tokens
    - Permission: Org member can view balance and history
  - [x] 6.4 Run feature-specific tests only
    - Run all tests related to this feature (tests from 1.1, 2.1, 3.1, 4.1, 5.1, and 6.3)
    - Expected total: approximately 22-28 tests
    - Do NOT run entire application test suite
    - Verify all critical workflows pass
  - [x] 6.5 Manual testing checklist
    - [x] Verify migration works on development database
    - [x] Test admin adding tokens via new UI
    - [x] Test company creation consumes global tokens
    - [x] Test token history page displays correctly
    - [x] Test module toggle still works without token display
    - [x] Test sidebar shows correct global balance

**Acceptance Criteria:**
- All feature-specific tests pass (22-28 tests total)
- Critical user workflows tested end-to-end
- No more than 8 additional tests added
- Manual testing checklist completed

---

## Execution Order

Recommended implementation sequence:

```
Phase 1: Database Layer (Task Group 1)
    |
    v
Phase 2: Data Migration (Task Group 2)
    |
    v
Phase 3: Backend API (Task Group 3)
    |
    v
Phase 4: Frontend Data Layer (Task Group 4)
    |
    v
Phase 5: Frontend UI (Task Group 5)
    |
    v
Phase 6: Integration Testing (Task Group 6)
```

**Critical Dependencies:**
- Task Group 2 depends on Task Group 1 (schema must exist before data migration)
- Task Group 3 depends on Task Group 2 (data must be migrated before API can use it)
- Task Group 4 depends on Task Group 3 (frontend needs backend endpoints)
- Task Group 5 depends on Task Group 4 (UI needs data layer)
- Task Group 6 depends on all previous groups

**Parallel Opportunities:**
- Within Task Group 5, components can be developed in parallel after composable is ready
- Tests within each group can be written first (TDD approach)

---

## Rollback Plan

If issues arise in production:

1. **Migration Rollback**: All Alembic migrations include down methods
2. **Code Rollback**: Revert to previous deployment
3. **Data Recovery**: Initial transaction records preserve original token sums

**Pre-deployment Checklist:**
- [x] Test migration on copy of production data
- [x] Verify token sums match expected values
- [x] Confirm transaction audit trail is complete
- [x] Test all endpoints with production-like data volume
