# Roles and Permissions System

## 🔐 Authentication & Authorization Overview

The Mint application uses **Keycloak** for authentication and authorization with a **role-based access control (RBAC)** system. Users are assigned roles that determine their permissions within workspaces.

## 🏢 Workspace-Based Multi-Tenancy

### Workspace Isolation
- **All data is workspace-scoped** - users can only access data within their assigned workspace(s)
- **Companies are workspace-scoped** - all workspace members see all companies in their workspace
- **Users can belong to multiple workspaces** with different roles in each

### Workspace Context
- Every API request includes workspace context via middleware
- Frontend components automatically filter data by current workspace
- Database queries include workspace_id filters for data isolation

## 👥 Available Roles

### Company Management Roles
- **`company.view`** - Can view companies in workspace
- **`company.create`** - Can create new companies
- **`company.update`** - Can edit existing companies
- **`company.delete`** - Can delete companies

### Task Management Roles
- **`task.view`** - Can view task results and status
- **`task.create`** - Can create new tasks (trigger data collection)
- **`task.restart`** - Can restart failed tasks

### Workspace Management Roles
- **`admin.workspaces`** - **SPECIAL ROLE** - Can manage all workspaces (global admin)
- **`workspace.manage`** - Can manage current workspace settings
- **`workspace.members`** - Can manage workspace members

### System Roles
- **`admin`** - Super admin with all permissions
- **`user`** - Basic user role (automatically assigned)

## 🔑 Permission System Implementation

### Frontend Permission Checking
```typescript
// In auth store (stores/auth.ts)
const { userRoles, userPermissions, hasRole, hasPermission } = useAuthStore()

// Check for specific role
if (hasRole('admin.workspaces')) {
  // Show workspace management UI
}

// Check for permission
if (hasPermission('company.update')) {
  // Show edit button
}
```

### Backend Permission Enforcement
```python
# In security.py
def verify_company_modify_permission(workspace_context: WorkspaceContext, permission: str):
    """Verify user has permission to modify companies in workspace"""
    if not workspace_context.has_permission(permission):
        raise HTTPException(status_code=403, detail="Insufficient permissions")
    return workspace_context

# Usage in endpoints
@router.put("/{company_id}")
async def update_company(
    company_id: int,
    workspace_context: WorkspaceContext = Depends(get_user_workspace),
    _: WorkspaceContext = Depends(lambda wc=Depends(get_user_workspace): 
        verify_company_modify_permission(wc, "company.update"))
):
```

### Sidebar Navigation
```vue
<!-- Show workspace management for admin.workspaces role -->
<template>
  <div v-if="userRoles.includes('admin.workspaces')">
    <NuxtLink to="/workspaces">
      <i class="fas fa-sitemap"></i>
      {{ $t('sidebar.workspaces') }}
    </NuxtLink>
  </div>
</template>
```

## 🏗️ Role Assignment

### Keycloak Configuration
Roles are managed in the Keycloak realm (`mint-dev`):

```json
{
  "roles": {
    "realm": [
      {
        "name": "admin.workspaces",
        "description": "Can manage all workspaces in the system"
      },
      {
        "name": "company.view",
        "description": "Can view companies in workspace"
      },
      {
        "name": "company.update", 
        "description": "Can update companies in workspace"
      },
      {
        "name": "company.delete",
        "description": "Can delete companies in workspace"
      }
    ]
  },
  "users": [
    {
      "username": "admin",
      "realmRoles": ["admin", "admin.workspaces", "company.view", "company.update", "company.delete"]
    }
  ]
}
```

### JWT Token Structure
```json
{
  "realm_access": {
    "roles": ["admin.workspaces", "company.view", "company.update"]
  },
  "resource_access": {
    "mint-front": {
      "roles": ["user"]
    }
  }
}
```

## 🔄 Permission Flow

### 1. User Login
1. User authenticates with Keycloak
2. Keycloak issues JWT token with assigned roles
3. Frontend extracts roles from token
4. Backend validates token and extracts permissions

### 2. Workspace Context
1. User selects/is assigned to workspace
2. Backend creates WorkspaceContext with user's roles in that workspace
3. All operations are filtered by workspace_id
4. Permissions are checked against workspace context

### 3. Data Access
1. Frontend requests data (e.g., companies)
2. Backend middleware extracts workspace context
3. Data is filtered by workspace_id
4. Only users with appropriate roles see/modify data

## 🛡️ Security Best Practices

### Frontend Security
- **Never trust frontend-only permission checks** - always validate on backend
- **Hide UI elements** based on permissions for UX, but expect backend validation
- **Store minimal user data** in frontend state
- **Clear auth state** on logout/token expiry

### Backend Security
- **Always validate permissions** in API endpoints
- **Use dependency injection** for consistent permission checking
- **Filter data by workspace_id** at database level
- **Audit log** permission-sensitive actions

### Database Security
- **Row-level security** through workspace_id filtering
- **No direct database access** without workspace context
- **Audit trails** for sensitive operations
- **Encrypted sensitive data** at rest

## 📋 Permission Matrix

| Action | Required Role | Workspace Scoped | Notes |
|--------|---------------|------------------|-------|
| View Companies | `company.view` | ✅ | See all companies in workspace |
| Create Company | `company.create` | ✅ | Creates in current workspace |
| Update Company | `company.update` | ✅ | Any company in workspace |
| Delete Company | `company.delete` | ✅ | Any company in workspace |
| View Tasks | `task.view` | ✅ | Task results for workspace companies |
| Create Tasks | `task.create` | ✅ | Trigger data collection |
| Restart Tasks | `task.restart` | ✅ | Restart failed tasks |
| Manage Workspace | `workspace.manage` | ✅ | Current workspace only |
| Manage All Workspaces | `admin.workspaces` | ❌ | **Global permission** |
| System Admin | `admin` | ❌ | All permissions everywhere |

## 🔧 Development Notes

### Adding New Permissions
1. **Define role in Keycloak** realm configuration
2. **Add permission check** in backend endpoint
3. **Update frontend UI** to show/hide based on role
4. **Add tests** for permission enforcement
5. **Update documentation** with new permission

### Testing Permissions
```typescript
// Mock user with specific roles for testing
const mockUser = {
  roles: ['company.view', 'company.update'],
  workspace_id: 1
}

// Test permission checking
expect(hasRole('company.update')).toBe(true)
expect(hasRole('admin.workspaces')).toBe(false)
```

## 🚨 Important Notes

### Global vs Workspace Permissions
- **`admin.workspaces`** is a **GLOBAL** role - not workspace-scoped
- **All other roles** are workspace-scoped
- **Users with `admin.workspaces`** can see and manage all workspaces
- **Regular users** only see their assigned workspace(s)

### Workspace Isolation
- **Companies belong to workspaces** - not individual users
- **All workspace members** see all companies in that workspace
- **Owner tracking** maintained for audit purposes only
- **Permissions are role-based** - not ownership-based

---

*Last updated: 2025-07-31*