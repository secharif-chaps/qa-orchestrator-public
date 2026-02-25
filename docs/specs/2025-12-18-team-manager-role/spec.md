# Specification: Team Manager Role

## Goal

Introduce a new `organization.manage` permission that grants team managers the ability to view and manage organization members (permissions, password reset) and access a global view of all organization folders.

## User Stories

- As a Team Manager, I want to view all members in my organization so that I can oversee team composition
- As a Team Manager, I want to change member permission levels (Reader/Writer/Manager) so that I can control access within my organization
- As a Team Manager, I want to view all folders in my organization (including private ones) so that I can monitor content across the team

## Specific Requirements

**Phase 0: Cleanup Old Team Page**
- Remove existing team page from frontend (`front/src/pages/team.vue`, `front/src/pages/team/` directory)
- Remove existing team components directory (`front/src/components/team/`)
- Remove backend team management endpoints (`back/app/api/endpoints/team_management.py`)
- Remove associated schemas (`back/app/schemas/team_management.py`)
- Clean up router registrations and imports referencing removed code
- Start fresh with new implementation after cleanup

**Phase 1: Backend - Keycloak and API Setup**
- Add `organization.manage` realm role to Keycloak configuration file
- Create new team management router at `/api/team` (not nested under organizations)
- `GET /api/team/members` - List organization members with pagination (10 per page)
- `PATCH /api/team/members/{userId}/permissions` - Update member permission tier
- `POST /api/team/members/{userId}/reset-password` - Reset member password (temporary password approach)
- All endpoints require `organization.manage` permission
- Exclude current user from permission modification actions (backend validation)

**Phase 2: Frontend - Settings Card and Team Management Page**
- Add new settings card on `/settings` page linking to team management
- Card only visible to users with `organization.manage` permission
- Create new page at `/settings/team-management` (breaks out of settings layout with dot notation)
- Page requires `organization.manage` route permission
- Members table displays: Avatar, Name, Email, Permission Level (Select), Actions Menu
- Permission Select shows Reader/Writer/Manager options with immediate save on change
- Actions Menu contains password reset option using existing `ResetPasswordModal.vue`
- Disable/hide permission selector and actions for current user's row
- Pagination with 10 items per page

**Phase 3: Folder Global View Feature**
- Add Toggle component to folders page header (My Folders / All Organization Folders)
- Toggle only visible to users with `organization.manage` permission
- Default view remains "My Folders" (existing behavior)
- Global view mode shows all organization folders (including private ones from other users)
- Add Owner column to table view in global mode
- Add owner info to grid view cards in global mode
- Add Tag component showing "Private" or "Shared" status per folder (only in global view)
- Backend endpoint: `GET /api/folders?global=true` (manager-only query param)

**Permission Tier Mapping**
- Reader: `organization.read`
- Writer: `organization.read` + `organization.write`
- Manager: `organization.read` + `organization.write` + `organization.manage`
- Backend syncs all roles atomically when permission tier changes

**Password Reset Flow**
- Reuse `front/src/components/admin/ResetPasswordModal.vue` component
- Component accepts user object with `user_id` and `username` properties
- Uses `useResetUserPassword` mutation from `@/mutations/admin-users`
- Generates temporary password that user must change on first login

## Visual Design

No visual mockups provided. Follow existing design patterns from:
- Settings page card layout (`front/src/pages/settings.vue`)
- Admin users table patterns in the codebase
- Existing folder page layout for global view toggle placement

## Existing Code to Leverage

**`front/src/components/admin/ResetPasswordModal.vue`**
- Full-featured password reset modal with temporary password generation
- Includes password validation, random generation, copy-to-clipboard
- Props: `user: AdminUserResponse` (may need type adjustment for team member)
- Emits: `close` event
- Uses `useResetUserPassword` mutation

**`front/src/mutations/admin-users.ts`**
- `useResetUserPassword` mutation calls `resetUserPassword` API function
- Accepts `{ userId, temporaryPassword }` parameters
- Shows success/error toasts automatically
- Can be reused directly for team management password reset

**`front/src/pages/folders/(list).vue`**
- Uses Vuellar Toggle component for view mode switching (grid/table)
- Pattern for conditional UI based on user permissions (`canCreateFolder`)
- Table and grid view dual-mode display pattern
- Owner column already exists in table view structure

**`front/src/pages/settings.vue`**
- Card-based navigation to settings sections
- Pattern for permission-gated settings cards
- Uses `sections` computed array for dynamic card rendering
- Route-based navigation with semantic card icons

**`back/app/services/keycloak_admin.py`**
- `sync_user_realm_roles` method for atomic role updates
- `get_user_realm_roles` method for fetching current permissions
- `get_organization_members` method for listing organization users
- Password reset capabilities through Keycloak Admin API

## Out of Scope

- Email-based password reset flow (using temporary password approach only)
- Manager ability to edit or delete other users' folders (view only)
- "Last active" or "Date added" columns in members table
- Confirmation dialogs for permission changes (immediate save is acceptable)
- Self-permission management (managers cannot change their own permissions)
- User creation from team management page (admin-only feature)
- Bulk permission changes (one user at a time)
- Folder search/filter in global view mode
- Export of team member list
- Audit logging of permission changes
