# Test Users for Permission Testing

This document contains credentials and details for test users with different permission combinations. Use these users to test the permission system implementation.

## How to Create Test Users

Run the following script from the mint-new repository root to create all test users:

```bash
./create_test_users.sh
```

**Note:** All test users are automatically linked to **Workspace ID 1** (ChapsVision workspace) and receive permissions within that workspace context.

## Permission Model

**Updated Permission Logic:**

- `workspace.read` - Permission to access the team page with read-only rights
- `workspace.write` - Permission to perform actions on users in the team page (add, edit, disable users)

## Test User Credentials

### 1. Admin User (Full Access)

```
Username: admin
Password: admin123
Email: admin@test.com
```

**Permissions:** Full admin access

- `workspace.read` - Can access team page (read-only)
- `workspace.write` - Can manage workspace users and settings
- `company.view` - Can view company details
- `company.create` - Can create companies
- `company.delete` - Can delete companies
- `admin.workspaces` - Admin access to workspace management

**Expected Behavior:**

- ✅ Can see all sidebar links (home, search, companies, team, workspaces)
- ✅ Can create companies (search page accessible, create button visible)
- ✅ Can view company lists and details
- ✅ Can access team page and manage users (add, edit, disable)
- ✅ Can access workspace management features
- ✅ Has access to all routes

### 2. Company Manager (Company Management Only)

```
Username: company_manager
Password: manager123
Email: manager@test.com
```

**Permissions:** Full company management, no team access

- `company.view` - Can view company details
- `company.create` - Can create companies
- `company.delete` - Can delete companies

**Expected Behavior:**

- ✅ Can see home, search, companies links
- ❌ Cannot see team link (no workspace.read)
- ❌ Cannot see workspaces link (no admin.workspaces)
- ✅ Can create, edit, and delete companies
- ✅ Can access company routes
- ❌ Cannot access team page (will get 403)
- ❌ Cannot access workspace admin routes (will get 403)

### 3. Company Editor (Edit Only)

```
Username: company_editor
Password: editor123
Email: editor@test.com
```

**Permissions:** Can edit existing companies, no team access

- `company.view` - Can view company details

**Expected Behavior:**

- ✅ Can see home, companies links
- ❌ Cannot see search link (no company.create)
- ❌ Cannot see team link (no workspace.read)
- ❌ Create button hidden in companies header
- ✅ Can view company details
- ✅ Edit actions should be available in company views
- ❌ Cannot access search page (will get 403)
- ❌ Cannot access team page (will get 403)

### 4. Company Creator (Create Only)

```
Username: company_creator
Password: creator123
Email: creator@test.com
```

**Permissions:** Can create new companies, no team access

- `company.view` - Can view company details
- `company.create` - Can create companies

**Expected Behavior:**

- ✅ Can see home, search, companies links
- ❌ Cannot see team link (no workspace.read)
- ✅ Can create companies (search page accessible)
- ✅ Create button visible in companies header
- ✅ Can view company details
- ❌ Edit/delete actions should be hidden
- ❌ Cannot modify existing companies
- ❌ Cannot access team page (will get 403)

### 5. Company Viewer (Companies Read Only)

```
Username: company_viewer
Password: viewer123
Email: viewer@test.com
```

**Permissions:** Read-only access to companies, no team access

- `company.view` - Can view company details

**Expected Behavior:**

- ✅ Can see home, companies links
- ❌ Cannot see search link (no company.create)
- ❌ Cannot see team link (no workspace.read)
- ❌ Create button hidden
- ✅ Can view company details
- ❌ All edit/delete/create actions should be hidden
- ❌ Cannot access search page (will get 403)
- ❌ Cannot access team page (will get 403)
- ✅ Should see "Read-only access" messages

### 6. Team Viewer (Team Read Only)

```
Username: team_viewer
Password: teamviewer123
Email: teamviewer@test.com
```

**Permissions:** Can view team page and companies, read-only access

- `workspace.read` - Can access team page (read-only)
- `company.view` - Can view company details

**Expected Behavior:**

- ✅ Can see home, companies, team links
- ❌ Cannot see search link (no company.create)
- ❌ Cannot see workspaces link (no admin.workspaces)
- ✅ Can access team page but in read-only mode
- ❌ Add user button should be hidden (no workspace.write)
- ❌ Edit/disable user buttons should be hidden (no workspace.write)
- ✅ Can view company details
- ❌ Cannot create/edit/delete companies

### 7. Team Manager (Team Management)

```
Username: team_manager
Password: teammanager123
Email: teammanager@test.com
```

**Permissions:** Can manage team members and view companies

- `workspace.read` - Can access team page (read-only)
- `workspace.write` - Can manage workspace users and settings
- `company.view` - Can view company details

**Expected Behavior:**

- ✅ Can see home, companies, team links
- ❌ Cannot see search link (no company.create)
- ❌ Cannot see workspaces link (no admin.workspaces)
- ✅ Can access team page with full management capabilities
- ✅ Add user button should be visible (has workspace.write)
- ✅ Edit/disable user buttons should be visible (has workspace.write)
- ✅ Can view company details
- ❌ Cannot create/edit/delete companies

### 8. Workspace Manager (Team Management + Companies View)

```
Username: workspace_manager
Password: workspace123
Email: workspace@test.com
```

**Permissions:** Can manage workspace users and view companies

- `workspace.read` - Can access team page (read-only)
- `workspace.write` - Can manage workspace users and settings
- `company.view` - Can view company details

**Expected Behavior:**

- ✅ Can see home, companies, team links
- ❌ Cannot see search link (no company.create)
- ❌ Cannot see workspaces link (no admin.workspaces)
- ✅ Can access team management features
- ✅ Can view company details
- ❌ Cannot create/edit/delete companies
- ❌ Cannot access search page (will get 403)

### 9. No Access User (No Permissions)

```
Username: no_access
Password: noaccess123
Email: noaccess@test.com
```

**Permissions:** No permissions assigned

**Expected Behavior:**

- ✅ Can only see home link in sidebar
- ❌ All other sidebar links should be hidden
- ❌ Should get 403 errors on most routes
- ❌ No company access, no team access, no admin access
- ❌ Should see minimal interface with permission denied messages

## Testing Scenarios

### Permission System Testing Checklist

#### Sidebar Navigation

- [ ] Admin: sees all links (home, search, companies, team, workspaces)
- [ ] Company Manager: sees home, search, companies (no team, no workspaces)
- [ ] Company Editor: sees home, companies (no search, no team)
- [ ] Company Creator: sees home, search, companies (no team)
- [ ] Company Viewer: sees home, companies (no search, no team)
- [ ] Team Viewer: sees home, companies, team (no search, no workspaces)
- [ ] Team Manager: sees home, companies, team (no search, no workspaces)
- [ ] Workspace Manager: sees home, companies, team (no search, no workspaces)
- [ ] No Access: sees only home

#### Company Creation

- [ ] Users with `company.create` can access `/search` page
- [ ] Users without `company.create` get 403 on `/search`
- [ ] Create button only visible to users with `company.create`
- [ ] Search sidebar link only visible to users with `company.create`

#### Company Management

- [ ] Users with `company.view` can access company pages
- [ ] Users without `company.view` get 403 on company pages
- [ ] Delete actions only visible to users with `company.delete`

#### Team Management

- [ ] Users with `workspace.read` can access `/team` page
- [ ] Users without `workspace.read` get 403 on `/team`
- [ ] Team sidebar link only visible to users with `workspace.read`
- [ ] Add user button only visible to users with `workspace.write`
- [ ] Edit user buttons only visible to users with `workspace.write`
- [ ] Disable/enable user buttons only visible to users with `workspace.write`
- [ ] Users with only `workspace.read` see team page in read-only mode

#### Route Protection

- [ ] `/search` requires `company.create`
- [ ] `/companies` requires `company.view`
- [ ] `/companies/[id]` requires `company.view`
- [ ] `/team` requires `workspace.read`
- [ ] Workspace admin routes require `admin.workspaces`

#### Error Handling

- [ ] 403 page displayed for unauthorized access
- [ ] Proper redirect from protected routes
- [ ] No console errors on permission checks

## Notes for Developers

### Creating New Test Scenarios

When adding new permissions or features:

1. **Add new permission to backend:** Update Keycloak realm configuration
2. **Create test user:** Add new user configuration to `create_test_users.sh`
3. **Update this document:** Document expected behavior
4. **Test systematically:** Use the checklist above

### Debugging Permission Issues

If permissions aren't working as expected:

1. **Check JWT token:** Use browser DevTools to inspect the access token
2. **Verify Keycloak roles:** Check user roles in Keycloak admin console
3. **Check database:** Verify `user_workspace_permissions` table entries
4. **Console logs:** Look for permission-related errors in browser console
5. **Network tab:** Check API responses for permission-related errors

### Permission Refresh

Remember that permissions are cached in the JWT token:

- Changes in Keycloak require user to log out and log back in
- Token refresh will pick up permission changes
- Database permission changes are reflected immediately on API calls
