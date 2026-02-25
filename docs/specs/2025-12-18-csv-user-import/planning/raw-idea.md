# CSV User Import Feature

## Overview
Add CSV/Excel import functionality for bulk user creation on two admin pages:
- Organization members page (`/admin/organizations/[organizationId]/members`)
- Global users admin page (`/admin/users`)

## UI/UX Requirements

### Entry Point
- Add "Import Users" button with secondary variant next to existing "Add User" button on both pages

### Flow
1. Click "Import Users" button → Navigate to dedicated import page
2. Upload CSV or Excel file (.csv, .xlsx)
3. Preview step with column mapping interface
4. Show validation results (duplicates, errors)
5. Confirm import
6. Show results summary + auto-download password CSV if generated

### Dedicated Import Pages
- `/admin/organizations/[organizationId]/members/import` - organization context
- `/admin/users/import` - global context with mandatory organization selector

## Column Mapping

### Required Columns
- **username**: Match variations like "username", "user_name", "login", "Username", "USER_NAME"
- **email**: Match variations like "email", "Email", "EMAIL", "e-mail", "mail", "Emails"

### Optional Columns
- **firstname**: Match "firstname", "first_name", "FirstName", "prenom", "given_name"
- **lastname**: Match "lastname", "last_name", "LastName", "nom", "family_name"
- **password**: Match "password", "Password", "pwd", "pass"

### Auto-matching
- Try to automatically detect and map columns based on common naming patterns
- Allow manual override of column mapping in preview step

## Password Handling

### Two Modes
1. **Password column found in CSV**: Use provided passwords (toggle off random generation)
2. **No password column**:
   - Show toggle "Generate random secure password for each user"
   - Default: ON

### Password Generation Rules
- 12 characters minimum
- Must include: uppercase, lowercase, numbers, symbols
- Use Keycloak's password generation or secure random generation

### Password Export
- If random passwords were generated, after successful import:
- Automatically trigger download of CSV with new "password" column
- CSV contains all imported users with their generated passwords

## Validation & Error Handling

### Duplicate Detection
- Check for duplicate usernames AND emails against existing users
- Display alert with list of duplicate emails that will be ignored
- Continue import for non-duplicate rows

### Partial Success
- If some rows fail, keep successful ones
- Report errors for failed rows with specific error messages
- Show summary: "X users imported successfully, Y failed"

### Validation Rules
- Username: required, unique
- Email: required, valid format, unique
- Password (if provided): must meet Keycloak policy
- Firstname/Lastname: optional, no validation

## Organization Assignment

### On Members Page (`/admin/organizations/[organizationId]/members/import`)
- Users automatically assigned to current organization (from route param)
- No organization selector needed

### On Global Users Page (`/admin/users/import`)
- Mandatory organization dropdown selector
- User must select target organization before import
- All imported users assigned to selected organization

## Permissions & Roles

### Default Role Assignment
- All imported users receive `organization.read` permission
- Future enhancement: support roles column in CSV

### First Login
- Users with generated passwords: force password change on first login
- Keycloak handles this via "temporary password" flag

## File Format Support
- CSV (.csv) - comma, semicolon, or tab delimited
- Excel (.xlsx)

## Component Architecture (Reuse Strategy)

### Shared Components
Create reusable components to minimize duplication:

1. **`UserImportWizard.vue`** - Main wizard component
   - Props: `organizationId?: string` (if provided, skip org selector)
   - Handles entire import flow
   - Emits: `import-complete`, `cancel`

2. **`FileUploader.vue`** - File selection component
   - Props: `acceptedFormats: string[]`
   - Handles CSV/Excel parsing
   - Emits: `file-parsed` with raw data

3. **`ColumnMapper.vue`** - Column mapping interface
   - Props: `columns: string[]`, `requiredFields`, `optionalFields`
   - Auto-suggests mappings
   - Allows manual override
   - Emits: `mapping-confirmed`

4. **`ImportPreview.vue`** - Preview table with validation
   - Props: `rows`, `mapping`, `duplicates`, `errors`
   - Shows which rows will be imported vs skipped
   - Displays alerts for issues

5. **`ImportResults.vue`** - Results summary
   - Props: `successCount`, `errorCount`, `errors`, `passwordsCsv?`
   - Shows success/failure summary
   - Triggers password CSV download if applicable

### Page Components (Thin Wrappers)
```
/pages/admin/organizations/[organizationId]/members/import.vue
  → Uses UserImportWizard with organizationId from route

/pages/admin/users/import.vue
  → Uses UserImportWizard without organizationId (shows selector)
```

### Composables
- `useUserImport()` - Core import logic, API calls, validation
- `useCsvParser()` - CSV/Excel parsing utilities
- `useColumnMatcher()` - Auto-matching logic for column names

## API Requirements

### Backend Endpoint
`POST /api/admin/users/import`

Request body:
```json
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

Response:
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

## No Email Notifications
- Keycloak email is disabled
- No welcome emails sent
- Passwords must be communicated via the downloaded CSV
