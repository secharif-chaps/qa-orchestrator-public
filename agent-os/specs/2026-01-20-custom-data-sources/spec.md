# Specification: Custom Data Sources

## Goal
Enable organizations to integrate external data providers (starting with Pappers) into the company screening workflow, allowing admins to configure API keys per organization and conditionally fetch additional company data during data collection.

## User Stories
- As a CSM admin, I want to configure API keys for external data sources per organization so that organizations can access additional company information during screening.
- As a system, I want to pass enabled data source credentials to Dify workflows so that data collection can conditionally fetch from external providers.

## Specific Requirements

**Sources Tab on Admin Organization Detail Page**
- Add a new "Sources" tab alongside existing Profile, Tokens, Members tabs
- Tab only visible to users with `admin.organizations` permission
- Tab displays a list of supported data sources as cards
- Each source card shows: logo, name, description, API key input, enable/disable state

**Data Source Card Component**
- Display source metadata (logo, name, description)
- API key input field with obfuscation (show first 4 and last 4 characters, middle masked)
- Edit mode toggle to reveal/update API key
- Enable/disable toggle that automatically enables when API key is saved
- Show created_at and updated_at timestamps in human-readable format

**API Key Security and Storage**
- Store API key in existing `OrganizationFeatureFlag.config` JSON field under `api_key` key
- Backend never returns full API key - only obfuscated version for display
- New endpoint to update source config with API key (separate from toggle endpoint)
- Feature flag auto-enabled when API key is set, auto-disabled when API key is removed

**Pappers as First Data Source**
- Add `PAPPERS = "pappers"` to `FeatureFlag` enum
- Pappers description: "French company data provider (legal info, financials, officers)"
- Add Pappers logo to frontend assets

**Raw Data Storage for Pappers**
- Add `raw_pappers_knowledge` column to Company model (String, nullable)
- Follow exact pattern of existing `raw_mistral_knowledge`, `raw_claude_knowledge` columns
- Include in `CompanyResponse` schema for API responses

**Dify Workflow Integration**
- Modify `_get_knowledge_data()` in DifyService to include `raw_pappers_knowledge`
- For data_collection workflow: pass `pappers_enabled` boolean and `pappers_api_key` string as inputs
- Dify workflow handles conditional logic internally based on these inputs
- Callback stores Pappers data in `raw_pappers_knowledge` field via `_update_company_data()`

**Feature Flag Service Extensions**
- Add `update_feature_config()` function to update config JSON without changing enabled state
- Extend `enable_feature()` to accept config parameter for initial API key setup
- Existing `get_feature_config()` already retrieves config for workflow use

**Backend API Endpoint for Source Configuration**
- `PUT /organizations/{org_id}/data-sources/{source}/config` - Update source API key
- Requires `admin.organizations` role
- Returns obfuscated API key in response
- Automatically enables feature flag when API key is set

## Visual Design
No visual mockups provided. Follow existing admin organization detail page patterns for tab and card layout.

## Existing Code to Leverage

**OrganizationFeatureFlag Model (`back/app/models/organization.py`)**
- `FeatureFlag` enum defines available flags (add PAPPERS)
- `OrganizationFeatureFlag` has `config` JSON field for storing API keys
- `enabled_at` timestamp already tracks when enabled

**Feature Flags Service (`back/app/services/feature_flags.py`)**
- `has_feature()` checks if flag is enabled for organization
- `get_feature_config()` retrieves config JSON for a flag
- `enable_feature()` and `disable_feature()` manage flag state
- Extend to support config updates

**Company Model (`back/app/models/company.py`)**
- Existing `raw_mistral_knowledge`, `raw_claude_knowledge`, `raw_wikipedia_knowledge`, `raw_scraped_website_knowledge` columns
- Add new column following same pattern

**DifyService (`back/app/services/dify.py`)**
- `_get_knowledge_data()` retrieves raw knowledge for downstream workflows
- `run_workflow()` passes inputs to Dify including knowledge data
- Data collection workflow inputs include feature flags

**Admin Organization Page (`front/src/pages/admin/organizations/[organizationId].vue`)**
- Uses Toggle component for tab navigation (Profile, Tokens, Members)
- Uses provide/inject for organization data to child components
- Child components in `[organizationId]/` directory (profile.vue, tokens.vue, members.vue)

## Out of Scope
- Multiple API keys per source (future phase)
- User-facing visibility of which sources are enabled (future phase)
- Single source data refresh capability (future phase)
- API key validation/test button (deferred)
- Source-specific data validation
- Usage analytics per data source
- Bulk API key management across organizations
- Non-Pappers data sources (Pappers only for Phase 1)
- Audit logging beyond created_at/updated_at timestamps
- API key rotation or expiration management
