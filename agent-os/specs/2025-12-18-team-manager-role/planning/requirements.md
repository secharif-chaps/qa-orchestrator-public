# Spec Requirements: Team Manager Role

## Initial Description

I would like to add a new role: the Team Manager

This role is for users with organization.manage realm roles

It should allow the user to access the team management page

The team management page should be accessible and appear in the settings page as a section card that links to this new page. If the user does not have the role it should not appear.

This is a completely new page - we should not reuse the team page we had, we should delete it and start from scratch for the frontend.

This page should allow the manager to view every member of his organization paginated and allow him to manage their permission (reader, writer, or manager).

Permission levels:
- reader: organization.read
- writer: organization.read + organization.write
- manager: organization.read + organization.write + organization.manage

He should also be able to reset the password the same way the admin can.

Another feature to implement after this is that the manager should be able to view every folder even the private ones of his organization users. Suggest the best way to do this - maybe adding a Toggle from vuellar to switch between his own folders and the global view in frontend is a good idea.

Also need to add this new permission to the keycloak init conf file.

## Requirements Discussion

### First Round Questions

**Q1:** For the members table, I'm assuming we should display: avatar, name, email, current permission level (reader/writer/manager), and an actions menu. Is that correct, or should we include additional columns like "last active" or "date added"?

**Answer:** Avatar, name, email, current permission level, actions menu - that's enough for now.

**Q2:** I assume the permission level selector should be a dropdown/select showing "Reader", "Writer", "Manager" options that immediately saves on change. Should there be a confirmation dialog before changing someone's permission level, or is immediate save acceptable?

**Answer:** No confirmation needed, immediate save is acceptable.

**Q3:** For password reset, I'm thinking of two approaches:
- Option A: Send a password reset email to the user (Keycloak's standard flow)
- Option B: Generate a temporary password and display it to the manager (similar to admin flow)

Which approach do you prefer? Or should both be available?

**Answer:** Option B - generate temporary password (same as admin feature). MUST reuse existing component: `front/src/components/admin/ResetPasswordModal.vue`

**Q4:** For the "view all folders" feature, I'm assuming this would be a Toggle component in the folders page header that switches between "My Folders" and "All Organization Folders". When viewing all folders, should the manager:
- See folders grouped by user/owner?
- See a flat list with an "Owner" column?
- Be able to edit/delete other users' folders, or just view them?

**Answer:** Both grid and table views should display owner information. Add a Tag component showing "Private" or "Shared" next to each folder - only visible when manager is in global view mode.

**Q5:** If a manager tries to change their own permission level (e.g., demote themselves from manager to reader), should we:
- Prevent this entirely with a clear error message?
- Allow it with a confirmation warning that they'll lose manager access?
- Hide the option to modify their own record?

**Answer:** Manager CANNOT manage their own permissions (hide or disable the option).

**Q6:** Should managers be able to modify other managers' permissions, or only readers/writers? (This determines the hierarchy - can managers demote each other?)

**Answer:** Managers CAN modify other managers' permissions.

**Q7:** I assume a default page size of 10 members with pagination controls is appropriate. Is that correct, or do you prefer a different size (20, 25) or infinite scroll?

**Answer:** 10 items per page is correct.

**Q8:** You mentioned deleting the old team page. Should we also remove the backend endpoints associated with the old team page, or keep them for potential future use?

**Answer:** YES - first cleanup phase to remove old team page code from both frontend AND backend, then start fresh (no legacy code evolution).

### Existing Code to Reference

**Similar Features Identified:**
- Component: `ResetPasswordModal` - Path: `front/src/components/admin/ResetPasswordModal.vue`
  - Full-featured password reset modal with temporary password generation
  - Uses `useResetUserPassword` mutation from `@/mutations/admin-users`
  - Includes password validation, generation, copy-to-clipboard functionality
  - Success state with password display and copy button
  - Props: `user: AdminUserResponse`
  - Emits: `close` event

### Follow-up Questions

No follow-up questions were needed.

## Visual Assets

### Files Provided:
No visual assets provided.

### Visual Insights:
N/A

## Requirements Summary

### Functional Requirements

#### 1. New Permission: organization.manage
- Add new Keycloak realm role: `organization.manage`
- Update Keycloak init configuration file with this new permission
- Permission hierarchy:
  - **Reader**: `organization.read`
  - **Writer**: `organization.read` + `organization.write`
  - **Manager**: `organization.read` + `organization.write` + `organization.manage`

#### 2. Team Management Page (New)
- Accessible from Settings page via a section card (visible only to users with `organization.manage`)
- New route/page for team management
- Features:
  - **Members Table**: Avatar, name, email, permission level, actions menu
  - **Permission Management**: Dropdown to change user role (Reader/Writer/Manager)
    - Immediate save on change (no confirmation dialog)
    - Current user's row is disabled/hidden from self-management
    - Managers can modify other managers' permissions
  - **Password Reset**: Reuse `ResetPasswordModal.vue` component
    - Generate temporary password approach (not email-based)
  - **Pagination**: 10 items per page

#### 3. Global Folder View (Manager Feature)
- Toggle component in folders page header to switch between:
  - "My Folders" (default view)
  - "All Organization Folders" (global view)
- In global view mode:
  - Both grid and table views display owner information
  - Tag component showing "Private" or "Shared" next to each folder
  - Tags only visible in global view mode
- Manager can view all folders (including private ones from other users)

#### 4. Cleanup Phase (Pre-Development)
- Remove old team page code from frontend
- Remove associated backend endpoints
- Start fresh with new implementation

### Reusability Opportunities

- **ResetPasswordModal.vue**: Reuse directly from `front/src/components/admin/ResetPasswordModal.vue`
  - May need to make it more generic (currently uses `AdminUserResponse` type)
  - Uses `useResetUserPassword` mutation - verify if this works for organization-level users
- **Vuellar Components**: Table, Toggle, Tag, Select, Pagination, Avatar, Menu
- **Permission composables**: Extend existing permission checking patterns

### Scope Boundaries

**In Scope:**
- New `organization.manage` permission in Keycloak
- Settings page card linking to team management (permission-gated)
- Team management page with member list, permission editing, password reset
- Global folder view toggle for managers
- Owner and privacy indicators in folder views
- Cleanup of old team page code (frontend and backend)
- Pagination (10 items per page)

**Out of Scope:**
- Email-based password reset (using temporary password approach only)
- Manager ability to edit/delete other users' folders (view only)
- "Last active" or "date added" columns (may be future enhancement)
- Confirmation dialogs for permission changes
- Self-permission management for managers

### Technical Considerations

- **Keycloak Configuration**: Update init config file with `organization.manage` role
- **Backend API**:
  - New endpoints for team management (list members, update permissions)
  - Endpoint for password reset (may reuse admin endpoint or create organization-scoped version)
  - Endpoint for global folder view (list all organization folders)
- **Frontend**:
  - New page: Team Management
  - Settings page: Add conditional card for team management link
  - Folders page: Add Toggle for global view, display owner/privacy info
  - Reuse `ResetPasswordModal.vue` component
- **Permission Checks**:
  - Route guard for team management page (`organization.manage` required)
  - UI hiding for settings card if user lacks permission
  - Disable/hide current user's row in team management table
- **Data Model**:
  - No new database tables (users managed in Keycloak)
  - Folder queries need to support organization-wide retrieval for managers

---

**Status**: Requirements gathered
**Date**: 2025-12-18
