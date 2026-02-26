# Requirements: Global Token System

## User Decisions from Spec Shaping

### Architecture Decision
**Question:** Where should the global token balance be stored?
**Answer:** Create a new `organizations` table for future-proofing - organization-level data will grow (preferences, settings, limits).

### Module System Decision
**Question:** Should we keep the module enabled/disabled concept separately from tokens?
**Answer:** Yes, keep modules for enabled/disabled state separately from tokens. This provides a clean separation of concerns.

### Migration Strategy
**Question:** How should we handle organizations where some modules have tokens but are disabled?
**Answer:** Sum all tokens regardless of enabled/disabled state.

### Transaction History Decision
**Question:** Should we add token transaction history for audit/tracking purposes?
**Answer:** Yes, create a `token_transactions` table logging all additions/consumptions.

### Stream Module Decision
**Question:** Should we keep the 'stream' module that only exists in frontend?
**Answer:** Remove stream module entirely - clean up the inconsistency. Only keep screen, target, explore.

### Refund Logic Decision
**Question:** When a company creation fails after tokens are consumed, how should refunds appear in history?
**Answer:** Do not implement refunds for now. Keep it simple.

### History Access Decision
**Question:** Should the token history page be admin-only or visible to organization members too?
**Answer:** Members can view too - they have a token sidebar to display balance, and history should be accurate.

### Audit User Tracking Decision
**Question:** For the 'created_by' field in transactions, what should we store?
**Answer:** Keycloak user ID only.

### User Sidebar Display Decision
**Question:** What should the token sidebar display for users?
**Answer:** Global balance + recent activity (last few transactions).

### History Filtering Decision
**Question:** Should the history endpoint support filtering?
**Answer:** Full filtering - filter by type, date range, reference_type with pagination.

### API Route Structure Decision
**Question:** What structure for new token API routes?
**Answer:** `/organizations/{id}/tokens` - Token routes directly on organization.

---

## Final Architecture Summary

### 3-Table Design
1. **organizations** - Organization-level settings (token_balance, future preferences)
2. **organization_modules** - Module configuration only (enabled/disabled, no tokens)
3. **token_transactions** - Complete audit history

### Transaction Types
- `add` - Manual token addition by admin
- `consume` - Token usage (company creation, etc.)
- `adjustment` - System adjustments (migration, corrections)

### Reference Types
- `company` - Company creation
- `csv_import` - Bulk CSV import
- `manual` - Manual admin operation
- `system` - System operation (migration, etc.)

### Access Permissions
- **View balance:** admin.organizations OR organization member
- **Add tokens:** admin.organizations only
- **View history:** admin.organizations OR organization.read

### Token Costs (unchanged)
- Company creation: 35 tokens per company
