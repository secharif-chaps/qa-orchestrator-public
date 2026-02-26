# Spec Requirements: CSV User Import

## Initial Description

Add CSV/Excel import functionality for bulk user creation on two admin pages:
- Organization members page (`/admin/organizations/[organizationId]/members`)
- Global users admin page (`/admin/users`)

The feature enables administrators to import multiple users at once from CSV or Excel files, with intelligent column mapping, duplicate detection, password generation, and comprehensive error handling.

## Requirements Discussion

### First Round Questions

**Q1:** I assume the wizard should have 4 steps (Upload, Map Columns, Review, Results). Is that correct?
**Answer:** Yes, confirmed 4-step wizard flow.

**Q2:** What are the specific UI elements needed for each step?
**Answer:**
- Step 1: Organization dropdown (only on `/admin/users/import`), drag & drop zone, file info display, max 100 rows validation
- Step 2: Auto-detected mappings, dropdown selectors for each field, required fields highlighted, warning banner for unmapped columns, password generation toggle
- Step 3: Preview table with masked passwords, duplicate detection alert, validation errors with row highlighting, summary text
- Step 4: Success/error counts, expandable error details, password CSV download button

**Q3:** How should duplicate detection and error handling work?
**Answer:**
- Duplicate emails should be listed in an alert and skipped from import
- Validation errors shown per row with red highlighting
- Summary shows "X users will be imported, Y will be skipped"

**Q4:** What happens if a user cancels mid-wizard?
**Answer:** Show confirmation modal: "Are you sure? Uploaded data will be lost"

**Q5:** What are the password generation requirements?
**Answer:** 12 characters with uppercase, lowercase, numbers, and symbols. Auto-trigger CSV download after import if passwords were generated.

**Q6:** What default permissions should imported users receive?
**Answer:** All imported users get `organization.read` permission.

**Q7:** Should Keycloak send welcome emails?
**Answer:** No, Keycloak email is disabled. Passwords must be communicated via downloaded CSV.

**Q8:** What are the column auto-matching patterns?
**Answer:** Provided comprehensive list:
- Username: username, user_name, login, Username, USER_NAME, user, utilisateur
- Email: email, Email, EMAIL, e-mail, mail, Emails, courriel, e_mail
- Firstname: firstname, first_name, FirstName, prenom, given_name, first, prenom
- Lastname: lastname, last_name, LastName, nom, family_name, last, name, surname
- Password: password, Password, pwd, pass, mot_de_passe

### Existing Code to Reference

No similar existing features identified for reference. This is a new import feature pattern for the application.

### Follow-up Questions

No follow-up questions needed - requirements are comprehensive.

## Visual Assets

### Files Provided:
No visual assets provided.

### Visual Insights:
N/A - No visuals to analyze.

## Requirements Summary

### Functional Requirements

#### Wizard Flow (4 Steps)

**Step 1: Upload File**
- Organization dropdown selector (mandatory, only on `/admin/users/import`)
- Drag & drop zone or file picker for CSV/Excel files
- Display file info after selection: name, size, row count
- Validation: maximum 100 rows, valid file format (.csv, .xlsx)
- Next button enabled only when file is valid AND organization is selected (if applicable)

**Step 2: Map Columns**
- Display auto-detected column mappings based on header names
- Dropdown selector for each field: username, email, firstname, lastname, password
- Required fields (username, email) visually highlighted
- Warning banner for unmapped CSV columns that will not be imported
- Password generation toggle:
  - Auto ON if no password column detected
  - Auto OFF if password column found
- Back / Next navigation buttons

**Step 3: Review & Confirm**
- Preview table showing mapped data
- Password column values masked with bullet characters
- Alert banner listing duplicate emails that will be skipped
- Validation errors shown per row with red highlighting
- Summary text: "X users will be imported, Y will be skipped"
- Back / Import buttons

**Step 4: Results**
- Success count with green checkmark icon
- Error count with expandable details showing specific errors
- If passwords were generated:
  - Auto-trigger CSV download containing passwords
  - Show "Download passwords" button for manual re-download
- Done button navigates back to user list page

#### Password Handling
- If password column found in CSV: use provided passwords
- If no password column: generate random passwords
- Password generation: 12 characters, uppercase + lowercase + numbers + symbols
- Force password change on first login (Keycloak temporary password flag)

#### User Assignment
- All imported users assigned to target organization
- All imported users receive `organization.read` permission
- No welcome emails (Keycloak email disabled)

#### Column Auto-Matching Patterns
| Field | Patterns |
|-------|----------|
| Username | username, user_name, login, Username, USER_NAME, user, utilisateur |
| Email | email, Email, EMAIL, e-mail, mail, Emails, courriel, e_mail |
| Firstname | firstname, first_name, FirstName, prenom, given_name, first, prenom |
| Lastname | lastname, last_name, LastName, nom, family_name, last, name, surname |
| Password | password, Password, pwd, pass, mot_de_passe |

### Reusability Opportunities

#### Shared Components (in `/components/admin/import/`)

1. **`UserImportWizard.vue`** - Main wizard with step management
   - Props: `organizationId?: string`
   - If organizationId provided, skip org selector in step 1

2. **`FileUploader.vue`** - Drag/drop + file picker
   - Props: `acceptedFormats: string[]`, `maxRows: number`
   - Emits: `file-parsed` with parsed data

3. **`ColumnMapper.vue`** - Column mapping interface
   - Props: `csvColumns: string[]`, `requiredFields`, `optionalFields`
   - Emits: `mapping-change`

4. **`ImportPreview.vue`** - Preview table with validation
   - Props: `rows`, `mapping`, `duplicates`, `errors`

5. **`ImportResults.vue`** - Results summary
   - Props: `successCount`, `errors`, `generatedPasswords`

#### Composables (in `/composables/`)

- `useUserImport()` - API calls, import orchestration
- `useCsvParser()` - CSV/Excel file parsing
- `useColumnMatcher()` - Smart column name matching with common variations

#### Page Components (thin wrappers)

- `/pages/admin/organizations/[organizationId]/members/import.vue`
- `/pages/admin/users/import.vue`

### Scope Boundaries

**In Scope:**
- 4-step wizard flow for user import
- CSV and Excel file support (.csv, .xlsx)
- Intelligent column auto-matching
- Manual column mapping override
- Duplicate email detection and skip
- Row-level validation with error display
- Password generation with configurable toggle
- Password CSV export (auto-download and manual button)
- Maximum 100 users per import
- Cancel confirmation modal
- Two entry points: org members page and global users page
- Default `organization.read` permission assignment
- Force password change on first login

**Out of Scope:**
- Custom role/permission assignment via CSV column (future enhancement)
- Email notifications (Keycloak email disabled)
- Import history/audit log
- Scheduled/recurring imports
- Import from other file formats (JSON, XML, etc.)

### Technical Considerations

#### Integration Points
- Keycloak Admin API for user creation
- Keycloak Organizations for user assignment
- Backend endpoint `POST /api/admin/users/import`

#### Existing System Constraints
- No users table in application database (users managed in Keycloak)
- No organization_members table (membership managed in Keycloak)
- Permission model: `organization.read`, `organization.write`, `admin.organizations`

#### Technology Stack
- Frontend: Vue 3 with Composition API, TypeScript, Vuellar components
- Backend: FastAPI with Pydantic validation
- Authentication: Keycloak with fastapi-keycloak integration
- File parsing: Client-side CSV/Excel parsing (consider Papa Parse for CSV, SheetJS for Excel)

#### API Contract

**Request:**
```json
POST /api/admin/users/import
{
  "organization_id": "uuid",
  "users": [
    {
      "username": "string",
      "email": "string",
      "firstname": "string?",
      "lastname": "string?",
      "password": "string?"
    }
  ],
  "generate_passwords": boolean
}
```

**Response:**
```json
{
  "success": [
    { "username": "user1", "email": "...", "password": "generated-if-applicable" }
  ],
  "errors": [
    { "row": 3, "username": "user2", "error": "Email already exists" }
  ]
}
```

#### Permission Requirements
- Requires `admin.organizations` permission to access both import pages
- Backend must verify permission before processing import
