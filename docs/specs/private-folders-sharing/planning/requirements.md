# Spec Requirements: Private Folders with Sharing & Simplified Permissions

## Initial Description

Transform the folder system from organization-wide visibility to private folders with user-level sharing, while simplifying the entire permission system.

**Key Changes:**
- Simplified permission system replacing granular company permissions
- Private folders by default (only owner and shared users can see)
- Folder sharing system with Reader/Writer roles
- Access control matrix for Owner, Writer, and Reader roles
- Admin permission management updates
- Data migration for existing folders

## Requirements Discussion

### First Round Questions

**Q1:** I assume `organization.read` will be the default permission for all users in an organization (i.e., users cannot exist in an organization without at least read access). Is that correct, or should some users have truly no access at all?
**Answer:** Confirmed. `organization.read` is the default permission for all users in an organization. All users have at least read access.

**Q2:** For the module-specific permissions (`screen.create`, `target.create`), I'm thinking these are additive permissions on top of `organization.write`. So a user with `organization.write` + `screen.create` can create folders AND create companies, but a user with only `organization.write` can create folders but NOT companies. Is this the intended behavior?
**Answer:** Confirmed. Module permissions are additive. A user needs `organization.write` + `screen.create` to create companies. `organization.write` alone allows folder creation but not company creation.

**Q3:** I assume we'll need to handle backward compatibility during migration - existing users with `company.create` will get `organization.write` + `screen.create`, while users with only `company.view` get `organization.read`. Should the migration be automatic, or should admins review and manually approve the new permission assignments?
**Answer:** User will handle Keycloak permission migration manually. No automatic migration needed in the application code.

**Q4:** For the share modal's user autocomplete search, I assume we search by both username and email, with results showing `username (email)` format. Should search results be limited (e.g., top 10 matches), or should we show all matching users in the organization?
**Answer:** Search results limited to top 10 matches. This is future-proof for large organizations.

**Q5:** When sharing with a user, I assume we show a simple dropdown/toggle for role selection (Reader/Writer). For users who only have `organization.read`, should we: (A) Show the Writer option but disabled with a tooltip explaining why, OR (B) Hide the Writer option entirely and only show Reader?
**Answer:** Option A - Show the Writer toggle but disabled for read-only users. This provides transparency about why the option is unavailable.

**Q6:** I assume the share modal should display the current list of shared users with their roles, allowing the owner to change roles or remove access inline. Should we include a "Share with everyone in organization" quick option, or keep sharing strictly to individual users?
**Answer:** No "share with everyone" option. Individual sharing only for now.

**Q7:** When a folder owner leaves the organization or is disabled, I'm assuming we should transfer ownership to the organization admin (or another designated user). Should we: (A) Automatically transfer to org admin with notification, OR (B) Flag the folder as "orphaned" and require admin action to claim/reassign, OR (C) Something else?
**Answer:** Option B - Flag folders as orphaned when owner leaves. No automatic transfer. Requires admin action to claim or reassign.

**Q8:** For the data migration of existing folders: the spec mentions folders have `owner_id` and `owner` fields. I assume `owner_id` contains the Keycloak UUID and `owner` contains the username for display. If both fields exist and are populated, is it safe to assume the migration will be straightforward (just making folders private to their existing owner)? Or are there folders with missing/null `owner_id` values we need to handle?
**Answer:** Database check confirmed ALL folders have `owner_id` populated (33 folders in production, 15 in local). Migration is straightforward - no orphaned or missing owner_id cases to handle.

**Q9:** Is there anything I'm assuming is included that should actually be OUT of scope for this feature?
**Answer:** Confirmed out of scope:
- Email/notification system for share invitations
- Batch sharing (share multiple folders at once)
- Share expiration dates
- Folder-level comments or activity log
- Sharing folders with external users (outside the organization)

### Existing Code to Reference

No specific similar features identified for reference. The spec-writer should investigate:
- Existing modal patterns in the codebase
- User search/autocomplete implementations if any exist
- Team management UI for permission-based conditional rendering patterns

### Follow-up Questions

No follow-up questions were needed.

## Visual Assets

### Files Provided:
No visual assets provided.

### Visual Insights:
N/A - No visual mockups or wireframes available. Implementation should follow existing Vuellar UI component patterns and application styling.

## Requirements Summary

### Functional Requirements

**Permission System:**
- Replace current permissions with simplified model:
  - `organization.read` - Base read-only access (default for all org users)
  - `organization.write` - Can create folders and manage owned folders
  - `screen.create` - Module permission to create Screen items (companies)
  - `target.create` - Module permission to create Target items (future)
  - `admin.organizations` - Super-admin access (keep existing)
- Module permissions are additive on top of base permissions
- Permission migration handled manually in Keycloak by admin

**Private Folders:**
- Folders are private to creator (owner) by default
- Only owner and explicitly shared users can see a folder
- Owner tracked via existing `owner_id` field (Keycloak user UUID)

**Folder Sharing:**
- Owner can share folder with specific users via modal
- Autocomplete search for users by username/email (within organization)
- Search results limited to top 10 matches
- Two share roles: Reader or Writer
- Share role constrained by user's Keycloak permissions:
  - `organization.write` user can be Writer or Reader
  - `organization.read` only user can only be Reader (Writer toggle disabled with explanation)
- No "share with everyone" option - individual sharing only

**Access Control Matrix:**

| Action | Owner | Shared Writer | Shared Reader |
|--------|-------|---------------|---------------|
| View folder and items | Yes | Yes | Yes |
| Edit folder (name, color, icon) | Yes | No | No |
| Delete folder | Yes | No | No |
| Manage sharing | Yes | No | No |
| Create items (if has module permission) | Yes | Yes | No |
| Delete items | Yes | No | No |

**User Experience by Permission Level:**

| Permission Level | Can Create Folders | Folder Access | Item Actions |
|------------------|-------------------|---------------|--------------|
| `organization.read` only | No | Own legacy + shared folders | Read-only (buttons disabled) |
| `organization.write` | Yes | Own + shared folders | Based on share role |
| `organization.write` + `screen.create` | Yes | Own + shared folders | Can create companies |

**Admin Permission Management:**
- Admin page shows base access toggle + module permissions
- Remove old `company.*` permissions from UI
- Add `screen.create`, `target.create` checkboxes

**Orphaned Folder Handling:**
- When folder owner leaves organization or is disabled, flag folder as "orphaned"
- No automatic ownership transfer
- Admin must manually claim or reassign orphaned folders

**Data Migration:**
- Existing folders become private to their owner
- All 33 production folders and 15 local folders have `owner_id` populated
- Migration is straightforward - no special handling needed

### Reusability Opportunities

- Vuellar Modal component for share dialog
- Vuellar Input/Searchbar for user autocomplete
- Vuellar Switch/Toggle for Reader/Writer role selection
- Vuellar Table for displaying shared users list
- Vuellar Button for actions (share, remove, save)
- Existing permission composables pattern for new permissions

### Scope Boundaries

**In Scope:**
- Simplified permission system implementation
- Private folders by default
- Folder sharing modal with user search
- Reader/Writer role assignment
- Access control enforcement (frontend and backend)
- Admin permission management UI updates
- Orphaned folder flagging mechanism
- Database migration for folder_shares table

**Out of Scope:**
- Email/notification system for share invitations
- Batch sharing (share multiple folders at once)
- Share expiration dates
- Folder-level comments or activity log
- Sharing with external users (outside the organization)
- Automatic Keycloak permission migration (handled manually by admin)

### Technical Considerations

**Database Changes:**
- New `folder_shares` table with: folder_id, user_id, role (reader/writer), created_at
- Possible `is_orphaned` flag on folders table (or derive from owner status)

**Backend:**
- New endpoints for folder sharing CRUD
- User search endpoint filtered by organization
- Update folder queries to filter by ownership/sharing
- Permission verification for all folder operations

**Frontend:**
- Share modal component with user autocomplete
- Permission-based UI conditional rendering updates
- New permission composables (`useFolderPermissions`)
- Update folder list/detail views for sharing indicators

**Keycloak:**
- New roles: `organization.read`, `organization.write`, `screen.create`, `target.create`
- Deprecate: `company.view`, `company.create`, `company.delete`
- Manual migration of existing user permissions

**Integration Points:**
- Keycloak user search API for autocomplete
- Existing folder service modifications
- Existing auth store updates for new permissions
