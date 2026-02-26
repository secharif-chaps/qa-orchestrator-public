# Custom Data Sources - Raw Idea

**Spec Name**: Custom Data Sources
**Date**: 2026-01-20
**Status**: Planning

## Overview

Allow organizations to integrate external data providers (starting with Pappers) into their company screening process. Admins can configure API keys per organization, and the system uses these sources during data collection workflows.

---

## User Requirements

### Phase 1: Custom Organization Source Management

**Admin Interface**:
- Admin page with new "Sources" tab on organization detail page
- Access restricted to admin.organizations permission only (CSM-only)
- CSM can enter API keys for supported data sources
- List of supported sources with:
  - Logo
  - Name
  - Description
  - API key field
- Feature flag per source (disabled until API key is filled)
- Uses existing organization-scoped feature flag system with config JSON for API keys

**Security & Display**:
- Obfuscate middle characters of API key, show start/end chars only
- No test button for API key validation
- Show created_at/updated_at timestamps only
- No additional metadata

### Phase 2: Custom Source Data Integration

**Data Collection Flow**:
- Data collection tasks check which sources are enabled for the organization
- Pass feature flags and API keys to Dify workflows
- Dify workflow conditionally fetches data from enabled sources (e.g., Pappers)
- Store raw data in new column on Company model (same pattern as existing raw_*_knowledge columns)
- Other tasks receive this raw knowledge as high-trust context for their prompts

---

## Technical Details Gathered

### Raw Data Storage Pattern

From `back/app/models/company.py`:
- Existing String columns:
  - raw_mistral_knowledge
  - raw_claude_knowledge
  - raw_wikipedia_knowledge
  - raw_scraped_website_knowledge
- Data flows from Dify webhook → Company model
- `_update_company_data()` method handles storage
- `_get_knowledge_data()` retrieves for downstream workflows

**Implementation Pattern**:
- Add new String column for each custom source (e.g., raw_pappers_knowledge)
- Follow same webhook update pattern
- Include in knowledge retrieval for downstream tasks

### Feature Flag System

From `back/app/models/organization.py`:
- OrganizationFeatureFlag model with FeatureFlag enum
- config JSON field can store API keys
- Service: feature_flags.py with:
  - `has_feature()`
  - `get_feature_config()`
  - `enable_feature()`
- API: `/organizations/{org_id}/feature-flags/{flag}`

**Implementation Pattern**:
- Add new feature flag per data source (e.g., PAPPERS_INTEGRATION)
- Store API key in config JSON field
- Use existing endpoints with new flag names

### Admin Organization Page

From `front/src/pages/admin/organizations/[organizationId].vue`:
- Current tabs: Profile, Tokens, Members
- Layout uses provide/inject pattern for organization data
- Component structure supports additional tabs

**Implementation Pattern**:
- Add new "Sources" tab to existing toggle
- Create new component for sources management
- Follow existing permission patterns (admin.organizations)

---

## User Answers to Clarifying Questions

1. **Admin UI Access**: Only admin.organizations permission (CSM-only)
2. **API Key Security**: Obfuscate middle characters, show start/end chars. No test button. created_at/updated_at only.
3. **Storage**: Same pattern as existing raw_*_knowledge columns (String column per source)
4. **Dify Integration**: Conditional logic inside workflow (workflow checks feature flags)
5. **Deferred Features**:
   - Multiple API keys per source
   - User visibility of which sources are enabled
   - Single source refresh capability

---

## Implementation Scope

### In Scope (Phase 1 & 2)
- Sources tab on admin organization detail page
- API key configuration per organization per source
- Feature flag system integration
- Raw data storage in Company model
- Dify workflow conditional data fetching
- Starting with Pappers as first integration

### Out of Scope (Future Phases)
- Multiple API keys per source
- User-facing source visibility
- Single source data refresh
- Source-specific data validation
- Usage analytics per source
- Bulk API key management across organizations

---

## Next Steps

1. Requirements research phase
2. Technical specification
3. Database migration design
4. Frontend component design
5. Backend API design
6. Dify workflow integration design
