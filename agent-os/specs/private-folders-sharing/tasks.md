# Task Breakdown: Private Folders with Sharing & Simplified Permissions

## Overview

**Total Tasks**: 35 tasks across 5 task groups
**Estimated Total Effort**: Large (multi-sprint feature)

This specification transforms the folder system from organization-wide visibility to private folders with user-level sharing, while simplifying the entire permission system. The implementation is organized into logical groups that can be executed by specialized engineers.

---

## Task List

### Backend Data Layer

#### Task Group 1: Database Models & Migrations
**Dependencies:** None
**Complexity:** Medium

- [x] 1.0 Complete FolderShare data model and migration
  - [x] 1.1 Write 4-6 focused tests for FolderShare model
    - Test FolderShare creation with valid data
    - Test unique constraint (folder_id + user_id)
    - Test role enum validation (reader/writer)
    - Test cascade delete when folder is deleted
    - Test relationship navigation (FolderShare -> Folder)
  - [x] 1.2 Create FolderShare model in `/mint-server/app/models/folder.py`
    - Fields: `id` (UUID, primary key), `folder_id` (FK to folders), `user_id` (String, Keycloak UUID), `user_username` (String, denormalized), `role` (Enum: reader/writer), `created_at` (DateTime)
    - Add unique constraint on (folder_id, user_id)
    - Add index on user_id for efficient lookup
    - Add relationship: `folder = relationship("Folder", back_populates="shares")`
  - [x] 1.3 Update Folder model with shares relationship and orphaned flag
    - Add `shares = relationship("FolderShare", back_populates="folder", cascade="all, delete-orphan")`
    - Add `is_orphaned = Column(Boolean, server_default=text("false"), nullable=False)`
  - [x] 1.4 Create Alembic migration for folder_shares table
    - Migration name: `add_folder_shares_table`
    - Include both upgrade and downgrade methods
    - Add indexes on folder_id, user_id
    - Add foreign key to folders table with CASCADE delete
  - [x] 1.5 Create Alembic migration for is_orphaned column
    - Migration name: `add_folder_is_orphaned_column`
    - Add is_orphaned boolean column to folders table
    - Default value: false
  - [x] 1.6 Ensure database layer tests pass
    - Run ONLY the 4-6 tests written in 1.1
    - Verify migrations run successfully in Docker
    - Do NOT run the entire test suite

**Acceptance Criteria:**
- The 4-6 tests written in 1.1 pass
- FolderShare model correctly validates role enum
- Unique constraint prevents duplicate shares
- Cascade delete removes shares when folder is deleted
- Migrations run successfully in both dev and production

---

### Backend Service Layer

#### Task Group 2: Folder Service & Access Control
**Dependencies:** Task Group 1
**Complexity:** Large

- [x] 2.0 Complete folder service layer with sharing and access control
  - [x] 2.1 Write 6-8 focused tests for folder sharing service
    - Test `share_folder()` creates share with correct role
    - Test `share_folder()` prevents duplicate shares
    - Test `unshare_folder()` removes share
    - Test `get_folder_shares()` returns all shares for folder
    - Test `list_folders()` returns owned and shared folders only
    - Test `has_folder_access()` returns true for owner
    - Test `has_folder_access()` returns true for shared user
    - Test `has_folder_access()` returns false for non-shared user
  - [x] 2.2 Create Pydantic schemas in `/mint-server/app/schemas/folder.py`
    - `FolderShareCreate`: user_id, role
    - `FolderShareUpdate`: role
    - `FolderShareResponse`: id, folder_id, user_id, user_username, role, created_at
    - `FolderShareRole` enum: reader, writer
  - [x] 2.3 Add sharing methods to FolderService
    - `share_folder(db, folder_id, user_id, user_username, role)` - Create share
    - `unshare_folder(db, folder_id, user_id)` - Remove share
    - `get_folder_shares(db, folder_id)` - List all shares for folder
    - `update_share_role(db, folder_id, user_id, role)` - Update share role
  - [x] 2.4 Add access control methods to FolderService
    - `has_folder_access(db, folder_id, user_id, organization_id)` - Check if user can view folder
    - `get_user_folder_role(db, folder_id, user_id)` - Returns 'owner', 'writer', 'reader', or None
    - `is_folder_owner(db, folder_id, user_id)` - Check if user is owner
  - [x] 2.5 Update `list_folders()` to filter by ownership + shares
    - Filter: `(owner_id == user_id) OR (has share with user_id)`
    - Use LEFT JOIN with folder_shares table
    - Maintain existing organization_id filter
    - Add `share_role` field to response indicating user's role
  - [x] 2.6 Update `get_folder()` to include access control
    - Check if user has access (owner or shared)
    - Return 404 if user has no access (not 403 for security)
    - Include `share_role` in response
  - [x] 2.7 Add `flag_folder_orphaned(db, folder_id)` method
    - Set `is_orphaned = True` on folder
    - For use when owner is disabled/removed
  - [x] 2.8 Ensure service layer tests pass
    - Run ONLY the 6-8 tests written in 2.1
    - Do NOT run the entire test suite

**Acceptance Criteria:**
- The 6-8 tests written in 2.1 pass
- Users can only see folders they own or have been shared with
- Share creation validates role correctly
- Access control methods work for all role types
- list_folders returns accurate share_role for each folder

---

### Backend API Layer

#### Task Group 3: API Endpoints & Permission Enforcement
**Dependencies:** Task Group 2
**Complexity:** Large

- [x] 3.0 Complete API endpoints with owner-based access control
  - [x] 3.1 Write 6-8 focused tests for folder sharing API
    - Test POST `/{folder_id}/shares` creates share (owner only)
    - Test POST `/{folder_id}/shares` returns 403 for non-owner
    - Test GET `/{folder_id}/shares` returns shares list (owner only)
    - Test DELETE `/{folder_id}/shares/{user_id}` removes share
    - Test PATCH `/{folder_id}/shares/{user_id}` updates role
    - Test PUT/DELETE folder returns 403 for shared writers
    - Test shared reader cannot create items
    - Test shared writer can create items (if has screen.create)
  - [x] 3.2 Create sharing endpoints in `/mint-server/app/api/endpoints/folder.py`
    - `POST /{folder_id}/shares` - Add share (owner only)
    - `GET /{folder_id}/shares` - List shares (owner only)
    - `DELETE /{folder_id}/shares/{user_id}` - Remove share (owner only)
    - `PATCH /{folder_id}/shares/{user_id}` - Update share role (owner only)
  - [x] 3.3 Create user search endpoint for share modal autocomplete
    - `GET /users/search?q={query}&limit=10` - Search users in organization
    - Leverage existing `keycloak_admin_service.get_organization_members()`
    - Return: user_id, username, email, has_write_permission
    - Filter by current user's organization
  - [x] 3.4 Update folder write endpoints with owner-only access
    - `PUT /{folder_id}` - Owner only (edit folder name/color/icon)
    - `DELETE /{folder_id}` - Owner only (soft delete)
    - `POST /{folder_id}/restore` - Owner only
    - Return 403 with clear message for non-owners
  - [x] 3.5 Update folder read endpoints with access control
    - `GET /` - Return only owned + shared folders
    - `GET /{folder_id}` - Check access before returning
    - Include `share_role` field in all folder responses
    - Include `is_owner` boolean field for clarity
  - [x] 3.6 Update item endpoints with role-based access
    - `POST /{folder_id}/items` - Owner or Writer (+ screen.create)
    - `DELETE /{folder_id}/items/{item_id}` - Owner only
    - Check both share role AND module permission
  - [x] 3.7 Add owner_id to folder response schema
    - Update FolderResponse and FolderWithItemsResponse schemas
    - Include `owner_id`, `is_owner`, `share_role` fields
  - [x] 3.8 Ensure API layer tests pass
    - Run ONLY the 6-8 tests written in 3.1
    - Do NOT run the entire test suite

**Acceptance Criteria:**
- The 6-8 tests written in 3.1 pass
- All sharing endpoints enforce owner-only access
- Non-owners receive 403 for write operations
- Shared users can view folders they have access to
- Item creation respects both share role and module permissions

---

### Frontend Implementation

#### Task Group 4: Frontend Components & Permission Logic
**Dependencies:** Task Group 3
**Complexity:** Large

- [x] 4.0 Complete frontend implementation with sharing modal and access control
  - [x] 4.1 Write 4-6 focused tests for folder permissions composable
    - Test `canCreateFolder` requires organization.write
    - Test `canEditFolder(folderId)` returns true for owner
    - Test `canDeleteFolder(folderId)` returns true for owner only
    - Test `canManageSharing(folderId)` returns true for owner only
    - Test `canCreateItems(folderId)` checks share role + screen.create
  - [x] 4.2 Create `useFolderPermissions.ts` composable
    - Location: `/front/src/composables/useFolderPermissions.ts`
    - Expose: `canCreateFolder`, `canEditFolder(folderId)`, `canDeleteFolder(folderId)`, `canManageSharing(folderId)`, `canCreateItems(folderId)`, `getUserFolderRole(folderId)`
    - Integrate with auth store for global permissions
    - Accept folder data with share_role field for context-aware checks
  - [x] 4.3 Create folder sharing API functions
    - Location: `/front/src/api/folders.ts`
    - `getFolderShares(folderId)` - GET /{folder_id}/shares
    - `createFolderShare(folderId, userId, userUsername, role)` - POST
    - `updateFolderShare(folderId, userId, role)` - PATCH
    - `deleteFolderShare(folderId, userId)` - DELETE
    - `searchUsersForSharing(query)` - GET /users/search
  - [x] 4.4 Create FolderShareModal component
    - Location: `/front/src/components/features/folders/FolderShareModal.vue`
    - Props: `folderId`, `isOpen`, `onClose`
    - User search with Vuellar Searchbar (autocomplete, top 10 results)
    - Role selection with Vuellar Switch (Reader/Writer toggle)
    - Disable Writer toggle for users without organization.write (show disabled, not hidden)
    - Current shares list with Vuellar Table
    - Remove share button per row
    - Follow existing Modal patterns in codebase
  - [x] 4.5 Create FolderShareButton component
    - Location: `/front/src/components/features/folders/FolderShareButton.vue`
    - Only visible to folder owner
    - Opens FolderShareModal on click
    - Use Vuellar Button with share icon
  - [x] 4.6 Update folder list page with sharing indicators
    - Location: `/front/src/pages/folders/`
    - Show "Shared with you" badge on shared folders
    - Show owner name on shared folders
    - Differentiate owned vs shared visually
    - Hide "Create Folder" button if user lacks organization.write
  - [x] 4.7 Update folder detail page with role-based actions
    - Location: `/front/src/pages/folders/[folderId].vue`
    - Show/hide edit button based on `canEditFolder()`
    - Show/hide delete button based on `canDeleteFolder()`
    - Show/hide share button based on `canManageSharing()`
    - Show/hide "Add item" button based on `canCreateItems()`
    - Disable all actions for readers with informative tooltip
  - [x] 4.8 Update auth store with new permission structure
    - Ensure `hasPermission()` works with new permissions
    - `organization.read`, `organization.write`, `screen.create`, `target.create`
    - Maintain backward compatibility during transition
  - [x] 4.9 Ensure frontend tests pass
    - Run ONLY the 4-6 tests written in 4.1
    - Do NOT run the entire test suite

**Acceptance Criteria:**
- The 4-6 tests written in 4.1 pass
- Share modal opens and functions correctly
- User search returns organization members with correct permissions
- Writer toggle is disabled (not hidden) for read-only users
- Folder actions are correctly shown/hidden based on role
- Visual distinction between owned and shared folders

---

### Admin & Final Integration

#### Task Group 5: Admin UI Updates & Integration Testing
**Dependencies:** Task Group 4
**Complexity:** Medium

- [x] 5.0 Complete admin UI updates and integration verification
  - [x] 5.1 Write 4-6 focused integration tests
    - Test full sharing flow: owner shares folder, recipient sees it
    - Test permission enforcement: writer cannot delete folder
    - Test permission enforcement: reader cannot create items
    - Test admin permission management updates roles correctly
  - [x] 5.2 Update admin permission management page
    - Location: `/front/src/pages/admin/users/`
    - Remove old `company.*` permissions from UI
    - Add base access section: `organization.read` (always on) + `organization.write` toggle
    - Add module permissions section: `screen.create`, `target.create` checkboxes
    - Show visual hierarchy: base permissions -> module permissions
  - [x] 5.3 Update admin user creation flow
    - Default new users to `organization.read` only
    - Add permission selection during user creation
    - Follow existing admin patterns
  - [x] 5.4 Update sidebar/navigation permission checks
    - Ensure navigation items respect new permissions
    - Hide "Create Folder" in sidebar if user lacks organization.write
    - Update any `company.*` permission checks to new model
  - [x] 5.5 Add folder ownership indicator in admin view
    - Show owner information in admin folder list (if exists)
    - Show orphaned status indicator
    - Prepare for future admin claim/reassign feature
  - [x] 5.6 Update TypeScript types for new permission model
    - Location: `/front/src/types/`
    - Add `FolderShare` interface (already done in Task Group 4)
    - Add `ShareRole` type (already done in Task Group 4)
    - Update `Folder` interface with `share_role`, `is_owner`, `owner_id` (already done in Task Group 4)
    - Add new permission type literals
  - [x] 5.7 Run integration tests and verify full flow
    - Run tests from 5.1
    - Test with different user permission combinations
    - Verify backward compatibility with existing features

**Acceptance Criteria:**
- The 4-6 tests written in 5.1 pass
- Admin UI shows new permission structure
- Old company.* permissions removed from UI
- Full sharing workflow functions correctly
- All user types see appropriate UI based on permissions

---

## Execution Order

Recommended implementation sequence:

1. **Task Group 1: Database Layer** (Backend Engineer)
   - Create FolderShare model and migrations
   - No dependencies, can start immediately

2. **Task Group 2: Service Layer** (Backend Engineer)
   - Depends on Task Group 1
   - Core business logic for sharing and access control

3. **Task Group 3: API Layer** (Backend Engineer)
   - Depends on Task Group 2
   - Expose sharing functionality via REST API

4. **Task Group 4: Frontend** (Frontend Engineer)
   - Depends on Task Group 3
   - Can start composable/types work in parallel with API
   - Share modal and permission-based UI

5. **Task Group 5: Admin & Integration** (Full Stack)
   - Depends on Task Group 4
   - Admin UI updates and final integration testing

---

## Technical Notes

### Permission Mapping (Old -> New)
| Old Permission | New Permission(s) |
|----------------|-------------------|
| `company.view` | `organization.read` |
| `company.create` | `organization.write` + `screen.create` |
| `company.delete` | `organization.write` + `screen.create` |
| `organization.read` | `organization.read` (unchanged) |
| `organization.write` | `organization.write` (unchanged) |
| `admin.organizations` | `admin.organizations` (unchanged) |

### Access Control Matrix Reference
| Action | Owner | Writer | Reader |
|--------|-------|--------|--------|
| View folder/items | Yes | Yes | Yes |
| Edit folder | Yes | No | No |
| Delete folder | Yes | No | No |
| Manage sharing | Yes | No | No |
| Create items* | Yes | Yes* | No |
| Delete items | Yes | No | No |

*Requires `screen.create` module permission in addition to Writer role

### Files to Modify
**Backend:**
- `/back/app/models/folder.py` - Add FolderShare, update Folder
- `/back/app/schemas/folder.py` - Add sharing schemas
- `/back/app/services/folder.py` - Add sharing and access methods
- `/back/app/api/endpoints/folder.py` - Add sharing endpoints, update access control
- `/back/alembic/versions/` - New migrations

**Frontend:**
- `/front/src/composables/useFolderPermissions.ts` - New file
- `/front/src/api/folders.ts` - Add sharing API functions
- `/front/src/components/features/folders/FolderShareModal.vue` - New file
- `/front/src/components/features/folders/FolderShareButton.vue` - New file
- `/front/src/pages/folders/*.vue` - Update for access control
- `/front/src/pages/admin/users/*.vue` - Update permission UI
- `/front/src/types/folder.ts` - Add sharing types
