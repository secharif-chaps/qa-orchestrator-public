# Private Folders with Sharing & Simplified Permissions

## Raw Idea

Transform the folder system from organization-wide visibility to private folders with user-level sharing, while simplifying the entire permission system.

## Key Changes

### 1. Simplified Permission System
Replace current permissions with:
- `organization.read` - Base read-only access to own/shared data
- `organization.write` - Can create folders and manage owned folders
- `screen.create` - Module permission to create Screen items (companies)
- `target.create` - Module permission to create Target items (watchfiles) - future
- `admin.organizations` - Super-admin access (keep existing)

Migration from old permissions:
- `company.view` → `organization.read`
- `company.create` → `organization.write` + `screen.create`
- `company.delete` → Removed (implicit: only owner can delete items)

### 2. Private Folders by Default
- Folders are private to creator (owner)
- Only owner and explicitly shared users can see the folder
- Owner tracked via `owner_id` (Keycloak user UUID)

### 3. Folder Sharing System
- Owner can share folder with specific users via modal
- Autocomplete search for users by username/email (within organization)
- Two share roles: Reader or Writer
- Share role constrained by user's Keycloak permissions:
  - `organization.write` user → can be Writer or Reader
  - `organization.read` only user → can only be Reader (toggle disabled)

### 4. Access Control Matrix

**Owner actions (full control):**
- View folder and items
- Edit folder (name, color, icon)
- Delete folder
- Manage sharing (add/remove users, change roles)
- Create items (if has module permission)
- Delete items

**Shared Writer actions (limited):**
- View folder and items
- Create items (if has module permission)
- CANNOT: Edit folder, delete folder, manage sharing, delete items

**Shared Reader actions (read-only):**
- View folder and items only
- All action buttons disabled

### 5. User Experience by Permission Level

**organization.read only user:**
- Cannot create folders
- Can access legacy folders they owned
- Can access folders shared with them
- All action buttons disabled (read-only)

**organization.write user:**
- Can create new folders
- Full control on owned folders
- Limited actions on shared folders (based on share role)

### 6. Admin Permission Management Updates
- Admin page shows base access toggle + module permissions
- Remove old `company.*` permissions from UI
- Add `screen.create`, `target.create` checkboxes

### 7. Data Migration
- Existing folders become private to their owner
- Need to verify: do folders have `owner_id` or only `username`?
- If only username: migration must lookup Keycloak user ID
- Folders without owner: assign to org admin or flag for review

## Existing Code References
- Folder model: `/mint-server/app/models/folder.py` (has owner_id and owner fields)
- Folder service: `/mint-server/app/services/folder.py`
- Team management API: `/mint-server/app/api/endpoints/team_management.py`
- Keycloak admin: `/mint-server/app/services/keycloak_admin.py`
- Auth store: `/mint-front/src/stores/auth.ts`
- Permission composables: `/mint-front/src/composables/useCompanyPermissions.ts`
