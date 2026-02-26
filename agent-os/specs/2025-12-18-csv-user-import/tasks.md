# Task Breakdown: CSV User Import

## Overview

Enable administrators to bulk import users from CSV or Excel files via a 4-step wizard, with intelligent column mapping, duplicate detection, password generation, and comprehensive error handling.

**Total Tasks:** 29 tasks across 4 phases

## Execution Order

1. **Phase 1: Foundation** - Backend API, types, and parsing utilities
2. **Phase 2: Shared Components** - Reusable wizard components
3. **Phase 3: Page Integration** - Import pages and entry points
4. **Phase 4: Polish & Testing** - Error handling, modals, and E2E tests

---

## Phase 1: Foundation

### Task Group 1: Backend API Endpoint
**Dependencies:** None

- [x] 1.0 Complete backend bulk user import endpoint
  - [x] 1.1 Write 4-6 focused tests for bulk import endpoint
    - Test successful bulk import with valid users
    - Test duplicate email detection and partial success
    - Test validation errors (invalid email format, missing required fields)
    - Test max row limit (100 users)
    - Test organization assignment and permission granting
  - [x] 1.2 Create Pydantic schemas for bulk import
    - `UserImportRow`: username, email, firstname, lastname, password (optional)
    - `BulkUserImportRequest`: organization_id, users list, generate_passwords flag
    - `UserImportResult`: user_id, username, email, success, error_message, generated_password (optional)
    - `BulkUserImportResponse`: success_count, error_count, results list
    - Files: `/back/app/schemas/user_import.py`
  - [x] 1.3 Create bulk import service function
    - Validate all users before creating any (collect errors)
    - Check for duplicate emails against existing Keycloak users
    - Generate random passwords if flag enabled (12 chars, mixed case, numbers, symbols)
    - Create users in Keycloak with temporary password flag
    - Assign users to specified organization
    - Grant `organization.read` permission to all imported users
    - Return detailed results per row (success/failure with error messages)
    - Files: `/back/app/services/user_import.py`
  - [x] 1.4 Create API endpoint
    - POST `/api/users/import`
    - Require `admin.organizations` permission
    - Accept JSON body with organization_id, users array, generate_passwords flag
    - Return structured response with success/error counts and per-row results
    - Files: `/back/app/api/endpoints/users.py` (add to existing)
  - [x] 1.5 Ensure backend tests pass
    - Run only the 4-6 tests written in 1.1
    - Verify Keycloak integration works correctly
    - Do NOT run entire backend test suite

**Files to Create/Modify:**
- Create: `/back/app/schemas/user_import.py`
- Create: `/back/app/services/user_import.py`
- Modify: `/back/app/api/endpoints/users.py`

**Acceptance Criteria:**
- Endpoint accepts bulk user data and returns detailed results
- Duplicate emails are detected and reported without failing entire import
- Passwords are generated when requested with correct complexity
- All users assigned to organization with `organization.read` permission
- Partial success supported (some rows succeed, others fail)

---

### Task Group 2: Frontend Types and API Functions
**Dependencies:** Task Group 1

- [x] 2.0 Complete frontend types and API integration
  - [x] 2.1 Create TypeScript interfaces for user import
    - `UserImportRow`: username, email, firstname, lastname, password
    - `ColumnMapping`: csvColumn, targetField, isRequired
    - `ValidationError`: row, field, message
    - `ImportResult`: userId, username, email, success, error, generatedPassword
    - `BulkImportResponse`: successCount, errorCount, results
    - `WizardStep`: upload, map, review, results
    - Files: `/front/src/types/user-import.ts`
  - [x] 2.2 Create API function for bulk import
    - `importUsers(organizationId, users, generatePasswords)` - POST to `/api/users/import`
    - Follow existing patterns from `/front/src/api/admin-users.ts`
    - Files: `/front/src/api/user-import.ts`
  - [x] 2.3 Create mutation for user import
    - `useImportUsers` mutation with toast notifications
    - Invalidate admin user queries on success
    - Follow patterns from `/front/src/mutations/admin-users.ts`
    - Files: `/front/src/mutations/user-import.ts`

**Files to Create:**
- `/front/src/types/user-import.ts`
- `/front/src/api/user-import.ts`
- `/front/src/mutations/user-import.ts`

**Acceptance Criteria:**
- All TypeScript interfaces properly typed
- API function handles request/response correctly
- Mutation provides loading state and error handling

---

### Task Group 3: CSV/Excel Parsing Composable
**Dependencies:** Task Group 2

- [x] 3.0 Complete file parsing composable
  - [x] 3.1 Write 3-4 focused tests for parsing composable
    - Test CSV parsing with headers and data rows
    - Test Excel (.xlsx) parsing
    - Test column auto-detection patterns
    - Test file validation (format, row count)
  - [x] 3.2 Create `useCsvParser` composable
    - Parse CSV files using native FileReader API or lightweight library
    - Parse Excel files using SheetJS (xlsx) library
    - Extract headers and data rows
    - Validate file format (.csv, .xlsx only)
    - Enforce max 100 rows limit
    - Return parsed data with file metadata (name, size, row count)
    - Files: `/front/src/composables/useCsvParser.ts`
  - [x] 3.3 Create `useColumnMapper` composable
    - Auto-detect column mappings based on header patterns from spec
    - Support manual override of mappings via dropdown
    - Track required fields (username, email) mapping status
    - Detect if password column exists in CSV
    - Files: `/front/src/composables/useColumnMapper.ts`
  - [x] 3.4 Ensure composable tests pass
    - Run only the 3-4 tests written in 3.1

**Files to Create:**
- `/front/src/composables/useCsvParser.ts`
- `/front/src/composables/useColumnMapper.ts`

**Acceptance Criteria:**
- CSV and Excel files parsed correctly
- Column patterns auto-detected with high accuracy
- Row limit enforced with clear error message
- File metadata (name, size, rows) available after parsing

---

## Phase 2: Shared Components

### Task Group 4: Wizard Shell Component
**Dependencies:** Task Group 2

- [x] 4.0 Complete wizard shell component
  - [x] 4.1 Write 2-3 focused tests for wizard component
    - Test step navigation (next, back, direct step click)
    - Test step indicator state (current, completed, upcoming)
    - Test disabled navigation when step requirements not met
  - [x] 4.2 Create `UserImportWizard` component
    - 4-step horizontal stepper: Upload, Map Columns, Review, Results
    - Current step highlighted, completed steps show checkmark
    - Manage step state and navigation
    - Emit events for step changes
    - Slot-based content for each step
    - Props: `currentStep`, `completedSteps`, `canProceed`
    - Use Vuellar Badge component for step indicators
    - Files: `/front/src/components/import/UserImportWizard.vue`
  - [x] 4.3 Ensure wizard tests pass

**Files to Create:**
- `/front/src/components/import/UserImportWizard.vue`

**Acceptance Criteria:**
- Step indicator shows correct states (current, completed, upcoming)
- Navigation disabled when step requirements not met
- Clean, accessible UI following existing admin patterns

---

### Task Group 5: File Upload Component
**Dependencies:** Task Groups 3, 4

- [x] 5.0 Complete file upload component
  - [x] 5.1 Write 2-3 focused tests for file uploader
    - Test drag-and-drop file acceptance
    - Test file validation (format, row count)
    - Test file info display after selection
  - [x] 5.2 Create `ImportFileUploader` component
    - Drag-and-drop zone with dashed border and icon
    - "Browse files" button alternative
    - Accept only .csv and .xlsx files
    - Display file name, size, and row count after selection
    - Show error for invalid files (wrong format, too many rows)
    - Use `useCsvParser` composable internally
    - Props: `modelValue` (parsed data), `disabled`
    - Emit: `update:modelValue`, `error`
    - Files: `/front/src/components/import/ImportFileUploader.vue`
  - [x] 5.3 Ensure file uploader tests pass

**Files to Create:**
- `/front/src/components/import/ImportFileUploader.vue`

**Acceptance Criteria:**
- Drag-and-drop works with visual feedback
- File validation with clear error messages
- File metadata displayed after successful selection

---

### Task Group 6: Column Mapper Component
**Dependencies:** Task Groups 3, 4

- [x] 6.0 Complete column mapper component
  - [x] 6.1 Write 2-3 focused tests for column mapper
    - Test auto-detection of column mappings
    - Test manual mapping override via dropdown
    - Test required field validation (username, email)
  - [x] 6.2 Create `ImportColumnMapper` component
    - Table showing CSV headers in first column
    - Dropdown selector with options: username, email, firstname, lastname, password, - ignore
    - Required fields (username, email) marked with red asterisk
    - Warning banner if any columns set to "ignore"
    - Password generation toggle with helper text
    - Auto-toggle based on password column detection
    - Use `useColumnMapper` composable internally
    - Props: `headers`, `modelValue` (mappings), `generatePasswords`
    - Emit: `update:modelValue`, `update:generatePasswords`
    - Use Vuellar Select, Switch, Alert components
    - Files: `/front/src/components/import/ImportColumnMapper.vue`
  - [x] 6.3 Ensure column mapper tests pass

**Files to Create:**
- `/front/src/components/import/ImportColumnMapper.vue`

**Acceptance Criteria:**
- Columns auto-mapped based on header patterns
- Manual override works via dropdown
- Required fields clearly indicated
- Password toggle state reflects CSV content

---

### Task Group 7: Import Preview Component
**Dependencies:** Task Group 6

- [x] 7.0 Complete import preview component
  - [x] 7.1 Write 2-3 focused tests for preview component
    - Test data table rendering with mapped columns
    - Test duplicate email detection alert
    - Test validation error highlighting
  - [x] 7.2 Create `ImportPreview` component
    - Data table showing mapped columns only
    - Password column values displayed as bullet characters
    - Alert banner listing duplicate emails with usernames
    - Rows with validation errors highlighted in red
    - Error tooltip on hover for invalid rows
    - Summary text: "X users will be imported, Y will be skipped"
    - Props: `users`, `mappings`, `duplicates`, `validationErrors`
    - Use Vuellar Table, Alert components
    - Files: `/front/src/components/import/ImportPreview.vue`
  - [x] 7.3 Ensure preview tests pass

**Files to Create:**
- `/front/src/components/import/ImportPreview.vue`

**Acceptance Criteria:**
- Table displays mapped data correctly
- Passwords masked with bullet characters
- Duplicates clearly listed in alert
- Validation errors visible with red highlighting

---

### Task Group 8: Import Results Component
**Dependencies:** Task Group 7

- [x] 8.0 Complete import results component
  - [x] 8.1 Write 2-3 focused tests for results component
    - Test success/error count display
    - Test expandable error details
    - Test password CSV download functionality
  - [x] 8.2 Create `ImportResults` component
    - Success card with green checkmark and count
    - Error section with expandable accordion per failed row
    - Password download section if passwords were generated
    - Auto-trigger password CSV download on mount
    - Manual "Download passwords" button for re-download
    - Done button with navigation callback
    - Props: `results`, `passwordsGenerated`
    - Emit: `done`
    - Use Vuellar Alert, Button components
    - Files: `/front/src/components/import/ImportResults.vue`
  - [x] 8.3 Create password CSV generation utility
    - Generate CSV with username, email, temporary_password columns
    - Trigger browser download with timestamp filename
    - Files: `/front/src/utils/downloadPasswordsCsv.ts`
  - [x] 8.4 Ensure results tests pass

**Files to Create:**
- `/front/src/components/import/ImportResults.vue`
- `/front/src/utils/downloadPasswordsCsv.ts`

**Acceptance Criteria:**
- Success/error counts clearly displayed
- Failed rows expandable with error details
- Password CSV auto-downloads and can be re-downloaded

---

## Phase 3: Page Integration

### Task Group 9: Organization Members Import Page
**Dependencies:** Task Groups 4-8

- [x] 9.0 Complete organization members import page
  - [x] 9.1 Create import page for organization context
    - Route: `/admin/organizations/[organizationId]/members/import`
    - Inject `organizationId` from parent layout (same pattern as members.vue)
    - Use all shared import components (wizard, uploader, mapper, preview, results)
    - No organization selector needed (uses current org)
    - Permission: inherit from parent (organization management)
    - Cancel navigates back to members page with confirmation
    - Files: `/front/src/pages/admin/organizations/[organizationId]/members.import.vue`
  - [x] 9.2 Wire up wizard state management
    - Track current step, parsed data, mappings, import results
    - Validate step completion before allowing navigation
    - Handle cancel with confirmation modal
    - Call import mutation on final step
  - [x] 9.3 Add translations for import wizard
    - Add keys under `admin.import.*` namespace
    - Files: `/front/src/locales/en.json`, `/front/src/locales/fr.json`

**Files to Create:**
- `/front/src/pages/admin/organizations/[organizationId]/members.import.vue`

**Files to Modify:**
- `/front/src/locales/en.json`
- `/front/src/locales/fr.json`

**Acceptance Criteria:**
- Page loads with organization context
- All wizard steps functional
- Import completes and shows results
- Cancel confirmation works correctly

---

### Task Group 10: Global Users Import Page
**Dependencies:** Task Groups 4-8

- [x] 10.0 Complete global users import page
  - [x] 10.1 Create import page for global admin context
    - Route: `/admin/users/import`
    - Include organization selector dropdown in Step 1
    - Organization selector required before proceeding
    - Use all shared import components
    - Permission: `admin.organizations`
    - Cancel navigates back to users page with confirmation
    - Files: `/front/src/pages/admin/users.import.vue`
  - [x] 10.2 Create organization selector component
    - Dropdown with all available organizations
    - Required validation
    - Use Vuellar Select component
    - Fetch organizations using existing `allOrganizationsQuery`
    - Files: `/front/src/components/import/OrganizationSelector.vue`
  - [x] 10.3 Wire up wizard with organization selection
    - Block Step 1 completion until organization selected
    - Pass selected organization to import mutation

**Files to Create:**
- `/front/src/pages/admin/users.import.vue`
- `/front/src/components/import/OrganizationSelector.vue`

**Acceptance Criteria:**
- Organization selector appears and is required
- All wizard steps functional
- Import assigns users to selected organization
- Cancel confirmation works correctly

---

### Task Group 11: Entry Point Buttons
**Dependencies:** Task Groups 9, 10

- [x] 11.0 Add import buttons to existing pages
  - [x] 11.1 Add "Import Users" button to global users page
    - Secondary variant button next to existing header
    - Navigate to `/admin/users/import`
    - Use Vuellar Button component
    - Files: Modify `/front/src/pages/admin/users.vue`
  - [x] 11.2 Add "Import Users" button to organization members page
    - Secondary variant button next to "Add User" button
    - Navigate to `/admin/organizations/[organizationId]/members/import`
    - Use Vuellar Button component
    - Files: Modify `/front/src/pages/admin/organizations/[organizationId]/members.vue`

**Files to Modify:**
- `/front/src/pages/admin/users.vue`
- `/front/src/pages/admin/organizations/[organizationId]/members.vue`

**Acceptance Criteria:**
- Buttons visible on both pages
- Navigation works correctly
- Consistent button styling (secondary variant)

---

## Phase 4: Polish & Testing

### Task Group 12: Cancel Confirmation Modal
**Dependencies:** Task Groups 9, 10

- [x] 12.0 Complete cancel confirmation behavior
  - [x] 12.1 Create cancel confirmation modal component
    - Modal text: "Are you sure? Uploaded data will be lost"
    - Confirm and Cancel buttons
    - Use Vuellar Modal component
    - Files: `/front/src/components/import/ImportCancelModal.vue`
  - [x] 12.2 Integrate modal with import pages
    - Show modal on Cancel button click
    - Show modal on browser back navigation (beforeRouteLeave guard)
    - Confirm navigates to appropriate list page
    - Cancel returns to wizard

**Files to Create:**
- `/front/src/components/import/ImportCancelModal.vue`

**Files to Modify:**
- `/front/src/pages/admin/users.import.vue`
- `/front/src/pages/admin/organizations/[organizationId]/members.import.vue`

**Acceptance Criteria:**
- Modal appears on cancel and navigation away
- Confirm discards data and navigates back
- Cancel keeps user in wizard

---

### Task Group 13: Error Handling Edge Cases
**Dependencies:** Task Groups 9-12

- [x] 13.0 Handle error edge cases
  - [x] 13.1 Add network error handling
    - Show error alert if API call fails
    - Allow retry without re-uploading file
    - Handle timeout gracefully
  - [x] 13.2 Add empty file handling
    - Detect and show error for files with no data rows
    - Clear error message: "File contains no data rows"
  - [x] 13.3 Add Keycloak error handling
    - Parse and display Keycloak-specific errors
    - Handle rate limiting, connection errors
  - [x] 13.4 Add file size validation
    - Warn for large files that may take time to process
    - Consider adding file size limit (e.g., 1MB)

**Files to Modify:**
- `/front/src/components/import/ImportFileUploader.vue`
- `/front/src/components/import/ImportResults.vue`
- `/front/src/mutations/user-import.ts`

**Acceptance Criteria:**
- All error states handled gracefully
- Clear, actionable error messages
- No unhandled exceptions or blank screens

---

### Task Group 14: E2E Tests
**Dependencies:** Task Groups 9-13

- [x] 14.0 Write E2E tests for critical user flows
  - [x] 14.1 Write E2E test for successful import flow
    - Navigate to import page
    - Upload valid CSV file
    - Verify auto-mapping
    - Proceed through wizard
    - Verify success results
    - Files: `/front/e2e/user-import.spec.ts`
  - [x] 14.2 Write E2E test for validation errors
    - Upload CSV with invalid data
    - Verify error highlighting in preview
    - Verify partial success handling
  - [x] 14.3 Write E2E test for cancel flow
    - Start import, upload file
    - Click cancel
    - Verify confirmation modal
    - Confirm and verify navigation

**Files to Create:**
- `/front/e2e/user-import.spec.ts`

**Acceptance Criteria:**
- Happy path test passes
- Error handling test passes
- Cancel flow test passes

---

## Summary

| Phase | Task Groups | Tasks | Focus Area |
|-------|-------------|-------|------------|
| Phase 1 | 1-3 | 12 | Backend API, Types, Parsing |
| Phase 2 | 4-8 | 10 | Shared Wizard Components |
| Phase 3 | 9-11 | 5 | Page Integration |
| Phase 4 | 12-14 | 4 | Polish & Testing |

**Critical Dependencies:**
- Task Group 1 (Backend) must complete before frontend can test end-to-end
- Task Groups 4-8 (Components) must complete before Task Groups 9-10 (Pages)
- Task Groups 9-10 must complete before Task Group 11 (Entry Points)

**Component Reuse Strategy:**
- All components in `/front/src/components/import/` are shared
- Organization members page and global users page use identical components
- Only difference: global page includes organization selector in Step 1
