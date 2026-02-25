# Specification: Global Token System

## Goal
Simplify the existing module-based token system to a single global token balance per organization with complete transaction history, improving maintainability and providing better audit capabilities.

## User Stories
- As an admin, I want to view and add tokens to an organization's global balance so that I can manage their credit allocation without dealing with per-module complexity
- As an organization member, I want to see my organization's token balance and usage history so that I can track consumption and plan accordingly

## Specific Requirements

**organizations table (NEW)**
- Create new table to store organization-level settings with Keycloak org UUID as primary key
- Columns: organization_id (VARCHAR PK), token_balance (Integer default 0), created_at, updated_at
- Organization records are auto-created when first token operation occurs (lazy initialization)
- No foreign key relationship to Keycloak - organization_id is a simple string matching Keycloak UUID
- Include index on organization_id for performance

**token_transactions table (NEW)**
- Create audit log table for all token operations with complete history
- Columns: id (Integer PK), organization_id (VARCHAR FK), amount (Integer +/-), balance_after (Integer), transaction_type (Enum: add, consume, adjustment), reference_type (Enum: company, csv_import, manual, system), reference_id (VARCHAR nullable), created_at (DateTime), created_by (VARCHAR Keycloak user ID)
- All token changes must create a transaction record for audit trail
- Include composite index on (organization_id, created_at) for efficient history queries

**organization_modules table (MODIFIED)**
- Remove token_count column entirely - tokens are now global
- Keep only: organization_id, module_name, enabled, created_at, updated_at
- Remove 'stream' module if any records exist (only screen, target, explore are valid)
- Continue to enforce enabled check before token consumption

**Token consumption flow**
- When consuming tokens, check global balance instead of module-specific balance
- Still check if module is enabled before allowing consumption
- Create transaction record with reference_type='company' and reference_id=company_id
- Use row-level locking on organizations table to prevent race conditions
- Existing 35 tokens per company creation cost remains unchanged

**API routes - New endpoints**
- GET /organizations/{id}/tokens - Return current balance (admin or org member)
- POST /organizations/{id}/tokens - Add tokens with amount in body (admin only, requires admin.organizations role)
- GET /organizations/{id}/tokens/history - Transaction history with filters: transaction_type, reference_type, date_from, date_to, page, size

**API routes - Code to Remove**
- DELETE endpoint: GET /{org_id}/modules/{module}/tokens - Remove entirely
- DELETE endpoint: POST /{org_id}/modules/{module}/tokens - Remove entirely
- MODIFY PUT /{org_id}/modules - Remove token_count from ModuleUpdateRequest schema
- Keep GET /{org_id}/modules endpoint (returns modules without token_count)
- Keep PUT /{org_id}/modules/{module}/toggle endpoint (module enablement)

**TokenManager service refactor**
- Replace module-based token methods with global balance operations
- New methods: get_balance(), add_tokens(), consume_tokens(), get_transaction_history()
- Keep module enablement check in consume_tokens() - must verify module is enabled
- All token operations create transaction records atomically
- REMOVE rollback_tokens method - per user decision, no refund logic for now
- If rollback needed in future, add as separate transaction type (not automatic)

**Database migration strategy**
- Migration 1: Create organizations table and token_transactions table
- Migration 2: For each org in organization_modules, sum all token_count values regardless of enabled state
- Migration 3: Insert organization record with summed balance, create initial transaction (type: adjustment, reference_type: system, note: "Migration from module-based tokens")
- Migration 4: Drop token_count column from organization_modules, delete 'stream' module records
- Test on copy of production data before deployment

**Frontend - OrganizationTokensManager.vue refactor**
- Remove per-module token display and management
- Display single global token balance prominently at top
- Add quick-add buttons for common amounts: 5, 10, 25, 50, 100 companies (x35 tokens each)
- Add custom amount input field
- Link to token history page

**Frontend - ModuleTokenCard.vue simplification**
- Remove token count display entirely
- Keep only enabled/disabled toggle switch
- Remove quick-add and custom amount controls
- Simplify component to focus on module enablement only

**Frontend - TokenSidebar.vue update**
- Display global balance using single query instead of summing modules
- Keep existing recent activity display (companies grouped by date)
- Update totalTokens computation to use new API response

**Frontend - New TokenHistoryPage.vue**
- Create new admin/org page at /tokens/history
- Table columns: Date, Type, Amount (+/-), Balance After, Reference, User
- Filters: transaction_type dropdown, date range picker
- Pagination with page size options (10, 25, 50)
- Accessible to both admins and org members with organization.read permission

**Frontend types update**
- Add TransactionType enum: 'add' | 'consume' | 'adjustment'
- Add ReferenceType enum: 'company' | 'csv_import' | 'manual' | 'system'
- Add TokenTransaction interface with all fields
- Add TokenBalanceResponse interface: { balance: number, organization_id: string }
- Remove ModuleName 'stream' option
- Remove token_count from ModuleConfig interface

## Existing Code to Leverage

**back/app/services/token_manager.py**
- Reuse TokenManager class structure but refactor methods from module-based to global
- Keep InsufficientTokensException class with updated error messaging
- Reuse row-level locking pattern (with_for_update) for race condition prevention
- Remove rollback_tokens method (no refund logic per requirements)

**back/app/models/organization.py**
- Extend with new Organization model class for organizations table
- Create new TokenTransaction model class in same file
- Keep OrganizationModule model, remove token_count column definition
- Keep ModuleName enum, ensure only screen/target/explore

**front/src/composables/useModuleTokens.ts**
- DELETE this file entirely
- Create new useGlobalTokens.ts with simpler API
- Keep refreshTokenData and cache invalidation patterns from original

**front/src/components/tokens/TokenCounter.vue**
- Reuse for global balance display, remove module-specific props
- Keep TOKENS_PER_COMPANY constant (35) for company equivalence calculation
- Keep loading and status indicator patterns

**front/src/queries/tokens.ts and mutations/tokens.ts**
- DELETE all module-specific queries and mutations
- Create new queries: organizationBalanceQuery, tokenHistoryQuery
- Create new mutation: useAddGlobalTokens
- Keep cache invalidation patterns on mutation success

## Out of Scope
- Refund logic for failed operations (tokens are consumed immediately, no automatic refund)
- WebSocket real-time token updates (polling is sufficient)
- Token transfer between organizations
- Token expiration or time-based limits
- Negative balance allowance (balance cannot go below 0)
- Per-user token tracking (tokens are organization-level only)
- Module-specific token costs (all modules use global pool)
- Token reservation system for pending operations
- Bulk token import via CSV for admins
- Token usage analytics or reporting dashboard
