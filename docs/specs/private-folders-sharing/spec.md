# Specification: Private Folders with Sharing & Simplified Permissions

## Goal

Transform the folder system from organization-wide visibility to private folders with user-level sharing, while simplifying the entire permission system to a cleaner, more maintainable model.

## User Stories

- As a folder owner, I want my folders to be private by default so that only I and people I explicitly share with can see them
- As a folder owner, I want to share folders with specific users in my organization with Reader or Writer roles so that I can collaborate while maintaining control over who can modify content

## Specific Requirements

**Simplified Permission System**
- Replace current granular permissions with new simplified model
- New permissions: `organization.read` (base), `organization.write` (create folders), `screen.create` (create companies), `target.create` (future watchfiles), `admin.organizations` (keep existing)
- Module permissions (`screen.create`, `target.create`) are additive on top of `organization.write`
- All organization users have `organization.read` by default
- Keycloak permission migration handled manually by admin (no automatic migration)

**Private Folders by Default**
- All folders are private to their owner (creator)
- Only owner and explicitly shared users can see a folder
- Owner tracked via existing `owner_id` field (Keycloak user UUID)
- Database confirmed: all existing folders have `owner_id` populated

**Folder Sharing Modal**
- Owner can share folder with specific users via modal on folder page
- Autocomplete search for users by username/email within organization
- Search results limited to top 10 matches for performance
- Two share roles available: Reader or Writer
- Display current shared users list with role and ability to remove

**Share Role Constraints**
- Users with `organization.write` can be assigned as Writer or Reader
- Users with only `organization.read` can only be assigned as Reader (Writer toggle disabled, not hidden)
- No "share with everyone" option - individual sharing only

**Access Control Matrix**
- Owner: full control (view, edit folder, delete folder, manage sharing, create items if has module permission, delete items)
- Shared Writer: view folder/items, create items if has module permission; CANNOT edit folder, delete folder, manage sharing, delete items
- Shared Reader: view folder/items only; all action buttons disabled

**User Experience by Permission Level**
- `organization.read` only: cannot create folders (button hidden/disabled), can access owned legacy folders and shared folders, all actions read-only
- `organization.write`: can create new folders, full control on owned folders, limited actions on shared folders based on share role

**Orphaned Folder Handling**
- When folder owner leaves organization or is disabled, flag folder as "orphaned"
- No automatic ownership transfer
- Admin must manually claim or reassign orphaned folders (future: `organization.admin` permission)

**Admin Permission Management Updates**
- Update admin page to show new permission structure
- Base access: `organization.read` (always on) + `organization.write` (optional toggle)
- Module permissions: checkboxes for `screen.create`, `target.create`
- Remove old `company.*` permissions from UI

## Visual Design

No visual mockups provided. Implementation should follow existing Vuellar UI component patterns and application styling conventions.

## Existing Code to Leverage

**`/mint-server/app/models/folder.py` - Folder Model**
- Already has `owner_id` and `owner` fields for tracking ownership
- Has `organization_id` for multi-tenancy filtering
- Add new `shares` relationship to `FolderShare` model
- Consider adding `is_orphaned` boolean flag

**`/mint-server/app/services/folder.py` - FolderService**
- `list_folders()` needs updating to filter by ownership + shares join
- `get_folder()` needs access control check for owner or shared user
- Add new methods: `share_folder()`, `unshare_folder()`, `get_folder_shares()`, `update_share_role()`

**`/mint-server/app/api/endpoints/folder.py` - Folder Endpoints**
- Update all write endpoints to check owner-only access
- Add new endpoints: `POST /{folder_id}/shares`, `GET /{folder_id}/shares`, `DELETE /{folder_id}/shares/{user_id}`, `PATCH /{folder_id}/shares/{user_id}`
- Update permission requirements from `organization.write` to owner-based checks

**`/mint-server/app/services/keycloak_admin.py` - User Search**
- `get_organization_members()` method already supports search and pagination
- Use this for autocomplete user search in share modal
- Leverage existing pattern for fetching user permissions

**`/mint-front/src/composables/useCompanyPermissions.ts` - Permission Composable Pattern**
- Create similar `useFolderPermissions.ts` composable
- Expose: `canCreateFolder`, `canEditFolder(folderId)`, `canDeleteFolder(folderId)`, `canManageSharing(folderId)`, `canCreateItems(folderId)`
- Integrate with auth store `hasPermission()` method

## Out of Scope

- Email/notification system for share invitations
- Batch sharing (share multiple folders at once)
- Share expiration dates
- Folder-level comments or activity log
- Sharing with external users (outside the organization)
- Automatic Keycloak permission migration (handled manually by admin)
- Automatic ownership transfer when user leaves organization
- `organization.admin` permission for orphan management (future feature)
