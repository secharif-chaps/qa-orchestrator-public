# Specification: CSV User Import

**Status:** ✅ Complete (2025-12-22)

## Goal

Enable administrators to bulk import users from CSV or Excel files via a 4-step wizard, with intelligent column mapping, duplicate detection, password generation, and comprehensive error handling.

## User Stories

- As an admin on the global users page, I want to import multiple users from a CSV file so that I can quickly onboard users without creating them one by one
- As an admin on the organization members page, I want to import users directly into the current organization so that new team members are automatically assigned to the correct organization

## Specific Requirements

**4-Step Wizard Flow**
- Step 1 (Upload): Organization selector (only on global import page), drag-and-drop file zone, display file name/size/row count after selection, validate max 100 rows and file format (.csv, .xlsx)
- Step 2 (Map Columns): Auto-detect column mappings using header patterns, dropdown selectors to override mappings, highlight required fields (username, email), show warning for unmapped columns, password generation toggle
- Step 3 (Review): Preview table with masked passwords, duplicate email detection alert, row-level validation errors with red highlighting, summary showing "X users will be imported, Y will be skipped"
- Step 4 (Results): Success/error counts with icons, expandable error details per failed row, auto-download password CSV if passwords were generated, manual re-download button for passwords

**Entry Points**
- Add "Import Users" button with secondary variant next to existing "Add User" button on `/admin/users` page
- Add "Import Users" button with secondary variant next to existing "Add User" button on `/admin/organizations/[organizationId]/members` page
- Buttons navigate to dedicated import pages: `/admin/users/import` and `/admin/organizations/[organizationId]/members/import`

**Column Auto-Matching Patterns**
- Username: username, user_name, login, Username, USER_NAME, user, utilisateur
- Email: email, Email, EMAIL, e-mail, mail, Emails, courriel, e_mail
- Firstname: firstname, first_name, FirstName, prenom, given_name, first, prenom
- Lastname: lastname, last_name, LastName, nom, family_name, last, name, surname
- Password: password, Password, pwd, pass, mot_de_passe

**Password Handling**
- If CSV contains password column: use provided passwords, toggle OFF by default
- If no password column detected: toggle ON to generate random passwords automatically
- Generated passwords: 12 characters with uppercase, lowercase, numbers, and symbols
- All imported users flagged as temporary password in Keycloak (force change on first login)
- Auto-trigger password CSV download after successful import if passwords were generated

**Validation Rules**
- Username: required, unique (check against existing Keycloak users)
- Email: required, valid email format, unique (check against existing Keycloak users)
- Password (if provided): must meet Keycloak password policy
- Firstname/Lastname: optional, no validation required
- Maximum 100 rows per import file

**Error Handling**
- Duplicate emails: list in alert banner and skip from import (do not fail entire import)
- Validation errors: show per row with specific error message and red row highlighting
- Partial success supported: successful rows imported even if some rows fail
- Summary text shows final counts after import completes

**User Assignment**
- All imported users assigned to selected/current organization
- All imported users receive `organization.read` permission by default
- No welcome emails sent (Keycloak email is disabled)

**Cancel Behavior**
- Show confirmation modal when user clicks Cancel or navigates away mid-wizard
- Modal text: "Are you sure? Uploaded data will be lost"
- Confirm discards data and navigates back to user list page

## Visual Design

No visual mockups provided. UI should follow existing admin page patterns and Vuellar component library.

**Wizard Step Indicator**
- Horizontal stepper showing 4 steps: Upload, Map Columns, Review, Results
- Current step highlighted, completed steps show checkmark
- Use existing Vuellar patterns or create simple horizontal flex layout with badges

**Step 1: Upload File UI**
- Card container with heading "Import Users"
- Organization dropdown selector (only on global import page, required)
- Drag-and-drop zone with dashed border, icon, and "Drop CSV or Excel file here" text
- Alternative "Browse files" link/button
- After file selected: display file name, size, and row count
- Next button disabled until file valid (and organization selected if applicable)
- Cancel button navigates back with confirmation

**Step 2: Map Columns UI**
- Table showing CSV column headers in first column
- Second column: dropdown selector with mapping options (username, email, firstname, lastname, password, - ignore)
- Required fields (username, email) marked with red asterisk
- Warning banner if any columns set to "ignore"
- Password generation toggle with label "Generate random passwords" and helper text
- Back and Next buttons

**Step 3: Review UI**
- Data preview table showing mapped columns
- Password column values displayed as bullet characters
- Alert banner listing duplicate emails with usernames that will be skipped
- Rows with validation errors highlighted in red with error tooltip
- Summary text: "X users will be imported, Y will be skipped due to errors/duplicates"
- Back and Import buttons

**Step 4: Results UI**
- Success card with green checkmark icon and count "X users imported successfully"
- Error section (if any): expandable accordion showing error details per row
- Password download section (if generated): auto-download triggered, "Download passwords" button for manual re-download
- Done button navigates back to user list page

## Existing Code to Leverage

**`/front/src/pages/admin/users.vue`**
- Reference for admin page structure, header layout, and permission handling
- Uses `adminUsersQuery` for fetching users with pagination
- Shows pattern for page with filters, table, pagination, and modals
- Route meta with `admin.organizations` permission requirement

**`/front/src/pages/admin/organizations/[organizationId]/members.vue`**
- Reference for organization-scoped user management page
- Injects `organizationId` from parent layout via Vue provide/inject
- Uses same `UsersTable` component and modal patterns
- Shows "Add User" button placement that import button should mirror

**`/front/src/mutations/admin-users.ts`**
- Reference for mutation patterns with `defineMutation`, `useMutation`, and `useQueryCache`
- Shows query cache invalidation after user operations
- Toast notifications for success/error states
- Pattern for handling async operations with loading states

**`/front/src/api/admin-users.ts`**
- Reference for API function patterns using `apiClient`
- Shows request body typing with `satisfies` operator
- Response typing patterns for user-related operations

**`/front/src/components/admin/UsersTable.vue`**
- Reference for table component structure with header, row, and empty state subcomponents
- Props interface and emit definitions for user actions
- Can be referenced for preview table design patterns

## Out of Scope

- Custom role/permission assignment via CSV column (only `organization.read` assigned)
- Email welcome notifications (Keycloak email is disabled)
- Import history or audit logging of past imports
- Scheduled or recurring automated imports
- Import from file formats other than CSV and Excel (.xlsx)
- Editing users after preview (must re-upload corrected file)
- Drag-and-drop reordering of column mappings
- Template CSV download (users provide their own files)
- User update/merge for existing users (only new user creation)
- Organization creation during import (organization must exist)
