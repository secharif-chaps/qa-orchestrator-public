# Task Breakdown: Custom Data Sources (Pappers Integration)

## Overview
Total Tasks: 26 sub-tasks across 5 task groups

This feature enables organizations to integrate external data providers (starting with Pappers) into the company screening workflow. Admins can configure API keys per organization, and the system conditionally fetches additional company data during data collection.

## Task List

### Database Layer

#### Task Group 1: Database Models and Migrations
**Dependencies:** None

- [ ] 1.0 Complete database layer for Pappers integration
  - [ ] 1.1 Write 4 focused tests for database changes
    - Test PAPPERS enum value exists in FeatureFlag
    - Test raw_pappers_knowledge column exists on Company model
    - Test OrganizationFeatureFlag config JSON can store api_key
    - Test feature flag enable/disable with config preserves api_key
  - [ ] 1.2 Add PAPPERS to FeatureFlag enum
    - File: `back/app/models/organization.py`
    - Add `PAPPERS = "pappers"` to FeatureFlag enum
    - Follow existing pattern (TRANSLATION = "translation")
  - [ ] 1.3 Add raw_pappers_knowledge column to Company model
    - File: `back/app/models/company.py`
    - Add `raw_pappers_knowledge = Column(String, nullable=True)`
    - Follow exact pattern of existing raw_*_knowledge columns
    - Update model docstring to document new field
  - [ ] 1.4 Create Alembic migration for both changes
    - Create single migration file for both enum and column changes
    - Migration name: "add_pappers_feature_flag_and_knowledge_column"
    - Add PAPPERS value to featureflag enum type
    - Add raw_pappers_knowledge column to companies table
    - Include downgrade logic (remove column, remove enum value)
  - [ ] 1.5 Ensure database layer tests pass
    - Run ONLY the 4 tests written in 1.1
    - Verify migration runs successfully in Docker
    - Do NOT run the entire test suite at this stage

**Acceptance Criteria:**
- The 4 tests written in 1.1 pass
- PAPPERS enum value available in FeatureFlag
- raw_pappers_knowledge column exists on Company model
- Migration runs without errors (upgrade and downgrade)

---

### Backend API Layer

#### Task Group 2: Feature Flags Service Extensions
**Dependencies:** Task Group 1

- [ ] 2.0 Complete feature flags service for data source configuration
  - [ ] 2.1 Write 5 focused tests for feature flag service
    - Test update_feature_config() updates config without changing enabled state
    - Test update_feature_config() creates record if not exists
    - Test enable_feature() with config parameter sets api_key
    - Test get_feature_config() retrieves api_key from config
    - Test auto-enable when api_key is set via update_feature_config()
  - [ ] 2.2 Add update_feature_config() function to feature_flags service
    - File: `back/app/services/feature_flags.py`
    - Accept: db, organization_id, flag, config dict
    - Update config JSON without changing enabled state
    - Auto-enable flag if config contains non-empty api_key
    - Auto-disable flag if config api_key is removed/empty
    - Log config updates (without logging api_key value)
  - [ ] 2.3 Update enable_feature() to merge config
    - Modify existing enable_feature() to merge new config with existing
    - Ensure api_key is preserved when re-enabling
  - [ ] 2.4 Add obfuscate_api_key() utility function
    - File: `back/app/services/feature_flags.py`
    - Return first 4 + "..." + last 4 characters
    - Return None if api_key is None or too short
  - [ ] 2.5 Ensure feature flag service tests pass
    - Run ONLY the 5 tests written in 2.1
    - Verify service functions work correctly
    - Do NOT run the entire test suite at this stage

**Acceptance Criteria:**
- The 5 tests written in 2.1 pass
- Config can be updated independently of enabled state
- API key obfuscation works correctly
- Auto-enable/disable based on api_key presence works

---

#### Task Group 3: API Endpoints and Dify Integration
**Dependencies:** Task Group 2

- [ ] 3.0 Complete API endpoints and Dify workflow integration
  - [ ] 3.1 Write 6 focused tests for API and Dify integration
    - Test PUT /organizations/{org_id}/data-sources/{source}/config endpoint
    - Test endpoint returns obfuscated API key in response
    - Test endpoint requires admin.organizations role
    - Test _get_knowledge_data() includes raw_pappers_knowledge
    - Test run_workflow() passes pappers_enabled and pappers_api_key for data_collection
    - Test _update_company_data() stores pappers data correctly
  - [ ] 3.2 Create data sources API endpoint
    - File: `back/app/api/endpoints/data_sources.py` (new file)
    - Add PUT /organizations/{org_id}/data-sources/{source}/config
    - Require admin.organizations role
    - Accept DataSourceConfigRequest with api_key field
    - Return DataSourceConfigResponse with obfuscated api_key
    - Call update_feature_config() from feature_flags service
  - [ ] 3.3 Add Pydantic schemas for data sources
    - File: `back/app/schemas/data_source.py` (new file)
    - DataSourceConfigRequest: api_key (str, optional)
    - DataSourceConfigResponse: source, enabled, api_key_masked, enabled_at, updated_at
    - DataSourceInfo: source, name, description (for listing available sources)
  - [ ] 3.4 Register data sources router in main.py
    - File: `back/app/main.py`
    - Import and include data_sources router
    - Prefix: /api (follows existing pattern)
  - [ ] 3.5 Update _get_knowledge_data() in DifyService
    - File: `back/app/services/dify.py`
    - Add "pappers" key to returned dictionary
    - Fetch raw_pappers_knowledge from Company model
    - Log pappers_chars in extra data
  - [ ] 3.6 Update run_workflow() for data_collection
    - File: `back/app/services/dify.py`
    - For data_collection task: fetch Pappers feature flag config
    - Pass pappers_enabled (boolean) in inputs
    - Pass pappers_api_key (string, can be empty) in inputs
    - Use get_feature_config() and has_feature() from feature_flags service
  - [ ] 3.7 Update _update_company_data() for Pappers
    - File: `back/app/services/company.py`
    - In data_collection branch, add: company.raw_pappers_knowledge = knowledge_data.get("pappers", "")
  - [ ] 3.8 Update CompanyResponse schema
    - File: `back/app/schemas/company.py`
    - Add raw_pappers_knowledge: str | None field
    - Follow pattern of existing raw_*_knowledge fields
  - [ ] 3.9 Ensure API and Dify integration tests pass
    - Run ONLY the 6 tests written in 3.1
    - Verify endpoint works correctly
    - Do NOT run the entire test suite at this stage

**Acceptance Criteria:**
- The 6 tests written in 3.1 pass
- Data source config endpoint works with proper authorization
- API key is never returned in full (always obfuscated)
- Dify receives pappers_enabled and pappers_api_key for data_collection
- Pappers data is stored in raw_pappers_knowledge field

---

### Frontend Layer

#### Task Group 4: Frontend UI Components
**Dependencies:** Task Group 3

- [ ] 4.0 Complete frontend Sources tab and components
  - [ ] 4.1 Write 4 focused tests for frontend components
    - Test Sources tab appears in organization detail toggle
    - Test DataSourceCard renders source info correctly
    - Test API key obfuscation display (shows first 4 + ... + last 4)
    - Test save API key mutation triggers correctly
  - [ ] 4.2 Add Pappers logo to frontend assets
    - File: `front/src/assets/logos/pappers.svg` (or .png)
    - Obtain official Pappers logo or create placeholder
    - Size: ~40x40px for card display
  - [ ] 4.3 Add Sources tab to organization detail Toggle
    - File: `front/src/pages/admin/organizations/[organizationId].vue`
    - Add "Sources" option to sectionOptions computed
    - Icon: fas fa-plug or fas fa-database
    - Label: t('organization.tabs.sources', 'Sources')
    - Update currentSection getter to handle /sources path
  - [ ] 4.4 Create sources.vue page component
    - File: `front/src/pages/admin/organizations/[organizationId]/sources.vue`
    - Inject organizationId from parent layout
    - Query data sources config for organization
    - Display DataSourceCard for each available source (Pappers only for now)
    - Handle loading and error states
  - [ ] 4.5 Create DataSourceCard component
    - File: `front/src/components/admin/DataSourceCard.vue`
    - Props: source (name, description, logo), config (enabled, api_key_masked, timestamps)
    - Display: logo, name, description
    - Display: obfuscated API key (or "Not configured" placeholder)
    - Edit mode: Input field for API key (password type)
    - Toggle: enable/disable switch
    - Show: created_at, updated_at in human-readable format
    - Use Vuellar components: Input, Switch, Button, Card pattern
  - [ ] 4.6 Add API functions for data sources
    - File: `front/src/api/data-sources.ts` (new file)
    - getDataSourceConfig(orgId, source): GET source config
    - updateDataSourceConfig(orgId, source, apiKey): PUT source config
  - [ ] 4.7 Add types for data sources
    - File: `front/src/types/data-source.ts` (new file)
    - DataSourceConfig interface: source, enabled, api_key_masked, enabled_at, updated_at
    - DataSourceInfo interface: source, name, description, logo
  - [ ] 4.8 Add queries and mutations for data sources
    - File: `front/src/queries/data-sources.ts` (new file)
    - dataSourceConfigQuery for fetching config
    - File: `front/src/mutations/data-sources.ts` (new file)
    - useUpdateDataSourceConfig mutation
  - [ ] 4.9 Add i18n translation keys
    - File: `front/src/locales/en.json` and `front/src/locales/fr.json`
    - Add translations for: organization.tabs.sources, dataSources.* keys
    - Include: title, description, pappers.name, pappers.description
    - Include: apiKey.label, apiKey.placeholder, apiKey.notConfigured
    - Include: save, cancel, edit, enable, disable, lastUpdated
  - [ ] 4.10 Ensure frontend tests pass
    - Run ONLY the 4 tests written in 4.1
    - Verify components render correctly
    - Do NOT run the entire test suite at this stage

**Acceptance Criteria:**
- The 4 tests written in 4.1 pass
- Sources tab appears in organization detail navigation
- DataSourceCard displays Pappers with correct info
- API key can be configured and is obfuscated in display
- Enable/disable toggle works correctly

---

### Testing & Integration

#### Task Group 5: Test Review and Integration Testing
**Dependencies:** Task Groups 1-4

- [ ] 5.0 Review existing tests and verify end-to-end flow
  - [ ] 5.1 Review tests from Task Groups 1-4
    - Review the 4 tests from database layer (Task 1.1)
    - Review the 5 tests from feature flags service (Task 2.1)
    - Review the 6 tests from API/Dify integration (Task 3.1)
    - Review the 4 tests from frontend (Task 4.1)
    - Total existing tests: 19 tests
  - [ ] 5.2 Analyze test coverage gaps for this feature
    - Identify critical user workflows lacking coverage
    - Focus ONLY on Custom Data Sources feature requirements
    - Prioritize end-to-end flow over unit test gaps
  - [ ] 5.3 Write up to 5 additional integration tests if needed
    - E2E test: Admin configures Pappers API key for organization
    - E2E test: Data collection workflow receives pappers inputs
    - E2E test: Pappers data is stored after callback
    - Integration test: Feature flag auto-enables when API key set
    - Integration test: API key obfuscation in all response paths
  - [ ] 5.4 Run feature-specific tests only
    - Run all tests related to Custom Data Sources feature
    - Expected total: approximately 19-24 tests
    - Verify all critical workflows pass
    - Do NOT run the entire application test suite

**Acceptance Criteria:**
- All feature-specific tests pass (19-24 tests total)
- End-to-end flow verified: configure API key -> data collection uses it -> data stored
- No more than 5 additional tests added
- API key never exposed in logs or responses

---

## Execution Order

Recommended implementation sequence:

1. **Task Group 1: Database Layer** (Day 1)
   - Add PAPPERS enum and raw_pappers_knowledge column
   - Create and run migration
   - Specialist: Backend Engineer

2. **Task Group 2: Feature Flags Service** (Day 1-2)
   - Extend feature flags service with config update capability
   - Add API key obfuscation utility
   - Specialist: Backend Engineer

3. **Task Group 3: API Endpoints & Dify Integration** (Day 2-3)
   - Create data sources endpoint
   - Update Dify service for Pappers integration
   - Update company data storage
   - Specialist: Backend Engineer

4. **Task Group 4: Frontend UI** (Day 3-4)
   - Add Sources tab to organization detail
   - Create DataSourceCard component
   - Implement API key configuration UI
   - Specialist: Frontend Engineer

5. **Task Group 5: Integration Testing** (Day 4)
   - Review all tests and fill critical gaps
   - Verify end-to-end flow
   - Specialist: QA Engineer

---

## Key Implementation Notes

### Existing Patterns to Follow

**Feature Flags:**
- `back/app/services/feature_flags.py` - Service functions
- `back/app/api/endpoints/feature_flags.py` - API endpoint pattern
- `back/app/models/organization.py` - OrganizationFeatureFlag model

**Raw Knowledge Columns:**
- `back/app/models/company.py` - Existing raw_*_knowledge columns
- `back/app/services/dify.py` - _get_knowledge_data() method

**Admin Organization Pages:**
- `front/src/pages/admin/organizations/[organizationId].vue` - Toggle navigation
- `front/src/pages/admin/organizations/[organizationId]/members.vue` - Subpage pattern

### Security Considerations

- API keys stored in OrganizationFeatureFlag.config JSON field
- Backend NEVER returns full API key - always obfuscated
- Use `obfuscate_api_key()` utility for consistent masking
- Log config updates without logging actual API key values

### Out of Scope (per spec)

- Multiple API keys per source
- User-facing visibility of enabled sources
- Single source data refresh capability
- API key validation/test button
- Non-Pappers data sources (Phase 1 is Pappers only)
