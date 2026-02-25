# Global Token System

## Problem
Currently tokens are linked to modules (screen, target, explore) per organization. Each module has its own token_count in the organization_modules table. This is unnecessarily complex since:
- Only the screen module actually consumes tokens
- Target and explore modules have token fields but no consumption logic
- The frontend even has a 4th "stream" module that doesn't exist in backend

## Solution
Simplify to a single global token balance per organization with proper transaction history.

## Key Decisions Made

### Architecture (3 tables)
1. **organizations** table (NEW) - Organization-level settings
   - organization_id (PK) - Keycloak org UUID
   - token_balance (Integer) - Global token balance
   - created_at, updated_at
   - Future: preferences, settings, limits

2. **organization_modules** table (SIMPLIFIED) - Module configuration only
   - organization_id, module_name (Composite PK)
   - enabled (Boolean) - No more token_count
   - Only 3 modules: screen, target, explore (remove stream)
   - created_at, updated_at

3. **token_transactions** table (NEW) - Audit history
   - id, organization_id
   - amount (+/-), balance_after
   - transaction_type: add, consume, adjustment
   - reference_type: company, csv_import, manual, system
   - reference_id (nullable - company_id, etc.)
   - created_at, created_by (Keycloak user ID)

### Migration Strategy
- Sum all module tokens regardless of enabled/disabled state
- Result becomes the global token_balance in organizations table
- Remove token_count column from organization_modules

### API Routes (New structure)
- GET /organizations/{id}/tokens - Get balance
- POST /organizations/{id}/tokens - Add tokens (admin only)
- GET /organizations/{id}/tokens/history - Transaction history with full filtering (date range, type, pagination)
- DELETE old module-specific token routes

### UI Changes
**Admin Interface:**
- Simplified token management on org admin page (balance only)
- New dedicated token history page

**User Interface:**
- Token sidebar shows global balance + recent activity
- Both admins and org members can view history

### Other Decisions
- No refund logic for now (failed operations don't refund)
- Track all operation types: add, consume, adjustment
- Store Keycloak user ID for audit trail
