# Permission System Guide

This guide explains how the permission-based access control system works in the Vue 3 application using unplugin-vue-router and Keycloak authentication.

## Table of Contents

1. [Overview](#overview)
2. [How Permissions Work](#how-permissions-work)
3. [Route-Level Permissions](#route-level-permissions)
4. [Component-Level Permission Checks](#component-level-permission-checks)
5. [Permission Utilities](#permission-utilities)
6. [Best Practices](#best-practices)
7. [Examples](#examples)

## Overview

The permission system is built on top of:

- **Keycloak**: Identity provider that manages roles and authentication
- **Vue Router**: Route-level protection with navigation guards
- **Pinia Store**: Reactive permission state management
- **unplugin-vue-router**: File-based routing with meta information

### Architecture

```
Keycloak Roles → JWT Token → Auth Store → Router Guards → Page Access
                                    ↓
                              Component Permissions
```

## How Permissions Work

### 1. Role-Based Permissions

Currently, permissions are derived directly from Keycloak roles:

```typescript
// In auth store
const userRoles = computed<string[]>(() => {
  if (!user.value?.profile) return []

  const token = jwtDecode(user.value?.access_token || '') as {
    realm_access: { roles: string[] }
  }

  return token.realm_access?.roles || []
})

const userPermissions = computed<string[]>(() => {
  // For now, treat roles as permissions
  return userRoles.value
})
```

### 2. Permission Storage

Permissions are stored in the auth store as computed properties that automatically update when the user's token changes:

- `userRoles`: Array of roles from Keycloak
- `userPermissions`: Array of permissions (currently mapped 1:1 with roles)

## Route-Level Permissions

### Using the `<route>` Block

Add permissions to any page using the `<route>` block with YAML syntax:

```vue
<template>
  <div>
    <!-- Your page content -->
  </div>
</template>

<route lang="yaml">
meta:
  permissions:
    - admin.workspaces
    - admin.users
</route>

<script setup lang="ts">
// Your component logic
</script>
```

### Permission Logic

- **OR Logic**: If multiple permissions are specified, the user needs **any one** of them to access the route
- **Access Denied**: Users without required permissions are redirected to `/403`
- **Public Routes**: Routes in `publicRoutes` array bypass permission checks

### Router Guard Implementation

The global navigation guard in `src/router/index.ts` handles permission checking:

```typescript
// Check for required permissions if specified in route meta
const requiredPermissions = to.meta.permissions as string[] | undefined
if (requiredPermissions && requiredPermissions.length > 0) {
  const hasPermission = requiredPermissions.some((permission) =>
    authStore.hasPermission(permission),
  )

  if (!hasPermission) {
    return next({
      path: '/403',
      replace: true,
    })
  }
}
```

## Component-Level Permission Checks

### Using Auth Store Methods

Import the auth store to check permissions within components:

```vue
<script setup lang="ts">
import { useAuthStore } from '@/stores/auth'

const authStore = useAuthStore()

// Check single permission
const canManageUsers = authStore.hasPermission('admin.users')

// Check multiple permissions (any)
const hasAdminAccess = authStore.hasAnyRole(['admin.workspaces', 'admin.users'])

// Check multiple permissions (all)
const hasFullAccess = authStore.hasAllRoles(['admin.workspaces', 'admin.users'])

// Get all user permissions
const userPermissions = authStore.userPermissions
</script>

<template>
  <div>
    <!-- Conditional rendering based on permissions -->
    <button v-if="canManageUsers">Manage Users</button>

    <!-- Show user's permissions for debugging -->
    <div v-if="userPermissions.length > 0">Permissions: {{ userPermissions.join(', ') }}</div>
  </div>
</template>
```

### Reactive Permission Checks

Since permissions are computed properties, they're automatically reactive:

```vue
<script setup lang="ts">
import { computed } from 'vue'
import { useAuthStore } from '@/stores/auth'

const authStore = useAuthStore()

// Reactive computed based on permissions
const availableActions = computed(() => {
  const actions = []

  if (authStore.hasPermission('admin.workspaces')) {
    actions.push('Manage Workspaces')
  }

  if (authStore.hasPermission('admin.users')) {
    actions.push('Manage Users')
  }

  return actions
})
</script>
```

## Permission Utilities

### Auth Store Methods

The auth store provides several utility methods:

### Single Permission Check

```typescript
hasPermission(permission: string): boolean
// Example: authStore.hasPermission('admin.workspaces')
```

### Role-Based Checks

```typescript
hasRole(role: string): boolean
hasAnyRole(roles: string[]): boolean  // OR logic
hasAllRoles(roles: string[]): boolean // AND logic
```

### Available Properties

```typescript
userRoles: string[]        // Keycloak roles
userPermissions: string[]  // Computed permissions
isAuthenticated: boolean   // Authentication status
```

### Resource-Based Composables

For better organization, use resource-specific permission composables:

#### Company Permissions Composable

```typescript
import { useCompanyPermissions } from '@/composables/useCompanyPermissions'

const {
  canCreateCompany,
  canEditCompany,
  canDeleteCompany,
  canViewCompany,
  canManageCompanies,
  hasAnyCompanyAccess
} = useCompanyPermissions()
```

**Individual permissions:**
- `canCreateCompany` - Permission to create companies
- `canEditCompany` - Permission to edit companies  
- `canDeleteCompany` - Permission to delete companies
- `canViewCompany` - Permission to view company details

**Compound permissions:**
- `canManageCompanies` - True if user can edit OR delete companies
- `hasAnyCompanyAccess` - True if user has any company permission

## Best Practices

### 1. **Permission Naming Convention**

Use a hierarchical naming system:

```
admin.workspaces     # Admin access to workspace management
admin.users          # Admin access to user management
workspace.read       # Permission to view workspace content
workspace.write      # Permission to manage workspace users and settings
company.create       # Permission to create companies
company.update         # Permission to edit companies
company.delete       # Permission to delete companies
company.view         # Permission to view company details
```

### 2. **Route Protection**

Always protect sensitive routes:

```vue
<!-- ✅ Good: Protected admin route -->
<route lang="yaml">
meta:
  permissions:
    - admin.workspaces
</route>

<!-- ❌ Bad: No protection on sensitive route -->
<route lang="yaml">
meta: {}
</route>
```

### 3. **UI Consistency**

Hide UI elements users can't access:

```vue
<template>
  <!-- ✅ Good: Hide actions user can't perform -->
  <div>
    <button v-if="authStore.hasPermission('company.update')" @click="editCompany">
      Edit Company
    </button>
    <button v-if="authStore.hasPermission('company.delete')" @click="deleteCompany">
      Delete Company
    </button>
  </div>

  <!-- ❌ Bad: Show buttons user can't use -->
  <div>
    <button @click="editCompany">Edit Company</button>
    <button @click="deleteCompany">Delete Company</button>
  </div>
</template>
```

### 4. **Error Handling**

Provide clear feedback when access is denied:

```vue
<template>
  <div v-if="!authStore.hasPermission('admin.workspaces')" class="text-secondary">
    You don't have permission to manage workspaces.
  </div>
</template>
```

### 5. **Debugging Permissions**

Add debug information in development:

```vue
<template>
  <!-- Only show in development -->
  <div v-if="import.meta.env.DEV" class="text-xs text-secondary">
    Debug: User permissions: {{ authStore.userPermissions.join(', ') }}
  </div>
</template>
```

## Examples

### Example 1: Admin-Only Page

```vue
<template>
  <div class="admin-dashboard">
    <h1>Admin Dashboard</h1>
    <p>This page requires admin.dashboard permission</p>

    <!-- Show current permissions for debugging -->
    <div class="text-sm text-secondary">
      Your permissions: {{ authStore.userPermissions.join(', ') }}
    </div>
  </div>
</template>

<route lang="yaml">
meta:
  permissions:
    - admin.dashboard
</route>

<script setup lang="ts">
import { useAuthStore } from '@/stores/auth'

const authStore = useAuthStore()
</script>
```

### Example 2: Multi-Permission Route

```vue
<template>
  <div class="content-management">
    <h1>Content Management</h1>
    <p>This page is accessible to content editors or admins</p>
  </div>
</template>

<route lang="yaml">
meta:
  permissions:
    - content.edit
    - admin.content
</route>

<script setup lang="ts">
// User needs EITHER 'content.edit' OR 'admin.content' permission
</script>
```

### Example 3: Conditional UI Elements

```vue
<template>
  <div class="company-actions">
    <h2>Company: {{ company.name }}</h2>

    <div class="actions">
      <!-- Show edit button only if user can edit -->
      <button v-if="canEdit" @click="editCompany" class="btn-primary">Edit Company</button>

      <!-- Show delete button only if user can delete -->
      <button v-if="canDelete" @click="deleteCompany" class="btn-danger">Delete Company</button>

      <!-- Show admin panel link only for admins -->
      <router-link v-if="isAdmin" to="/admin" class="btn-secondary"> Admin Panel </router-link>
    </div>

    <!-- No permissions message -->
    <div v-if="!canEdit && !canDelete" class="text-secondary">
      You have read-only access to this company.
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useAuthStore } from '@/stores/auth'

const authStore = useAuthStore()

// Computed permission checks
const canEdit = computed(() => authStore.hasPermission('company.update'))
const canDelete = computed(() => authStore.hasPermission('company.delete'))
const isAdmin = computed(() => authStore.hasAnyRole(['admin.companies', 'admin.all']))

// Mock company data
const company = { name: 'Example Company' }

const editCompany = () => {
  // Edit logic
}

const deleteCompany = () => {
  // Delete logic
}
</script>
```

### Example 4: Role-Based Navigation

```vue
<template>
  <nav class="sidebar">
    <ul>
      <li><router-link to="/">Dashboard</router-link></li>
      <li><router-link to="/companies">Companies</router-link></li>

      <!-- Admin-only navigation items -->
      <template v-if="hasAdminAccess">
        <li><router-link to="/admin/workspaces">Workspaces</router-link></li>
        <li><router-link to="/admin/users">User Management</router-link></li>
      </template>

      <!-- Content management for editors and admins -->
      <li v-if="canManageContent">
        <router-link to="/content">Content Management</router-link>
      </li>

      <!-- Settings for all authenticated users -->
      <li><router-link to="/settings">Settings</router-link></li>
    </ul>
  </nav>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useAuthStore } from '@/stores/auth'

const authStore = useAuthStore()

const hasAdminAccess = computed(() => authStore.hasAnyRole(['admin.workspaces', 'admin.users']))

const canManageContent = computed(() => authStore.hasAnyRole(['content.edit', 'admin.content']))
</script>
```

## Troubleshooting

### Common Issues

1. **403 Errors**: Check that the user has the required permission in Keycloak
2. **Route Access**: Ensure permissions are correctly specified in `<route>` blocks
3. **Token Expiry**: Permissions are refreshed when tokens are renewed
4. **Case Sensitivity**: Permission names are case-sensitive

### Debugging Tips

1. Check browser console for permission warnings
2. Inspect `authStore.userPermissions` in Vue DevTools
3. Verify JWT token contents in browser DevTools
4. Test with different user roles in Keycloak

## Migration Notes

If you need to evolve from role-based to permission-based access:

1. Update the `userPermissions` computed property in the auth store
2. Create a role-to-permission mapping system
3. Update existing route permissions gradually
4. Test thoroughly with different user roles

This system provides flexible, secure access control while maintaining good developer experience with Vue 3 and TypeScript.

### Example 5: Company Permissions with Composable

```vue
<template>
  <div class="company-header">
    <h1>Companies</h1>
    
    <!-- Search button - only visible if user can create companies -->
    <router-link v-if="canCreateCompany" to="/search" class="btn-primary">
      <i class="fas fa-plus"></i>
      Create Company
    </router-link>
    
    <!-- Company actions for individual companies -->
    <div v-for="company in companies" :key="company.id" class="company-item">
      <h3>{{ company.name }}</h3>
      
      <div class="company-actions">
        <button v-if="canEditCompany" @click="editCompany(company.id)">
          Edit
        </button>
        <button v-if="canDeleteCompany" @click="deleteCompany(company.id)">
          Delete
        </button>
      </div>
      
      <!-- Show message if user has no management permissions -->
      <p v-if="!canManageCompanies" class="text-secondary">
        Read-only access
      </p>
    </div>
  </div>
</template>

<script setup lang="ts">
import { useCompanyPermissions } from '@/composables/useCompanyPermissions'

// Get all company permissions with one import
const {
  canCreateCompany,
  canEditCompany,
  canDeleteCompany,
  canManageCompanies
} = useCompanyPermissions()

const companies = ref([]) // Your company data

const editCompany = (id: string) => {
  // Edit logic
}

const deleteCompany = (id: string) => {
  // Delete logic
}
</script>
```

### Example 6: Team Management Page

```vue
<template>
  <div class="team-management">
    <h1>Team Management</h1>
    <p>Manage users in your workspace</p>

    <!-- Team management requires workspace.write permission -->
    <TeamUserModal
      v-if="showModal"
      :user="editingUser"
      @update-user="updateUser"
      @create-user="createUser"
    />
  </div>
</template>

<route lang="yaml">
meta:
  permissions:
    - workspace.write
</route>

<script setup lang="ts">
import { useAuthStore } from '@/stores/auth'

const authStore = useAuthStore()

// This page is only accessible to users with workspace.write permission
// The router guard will automatically redirect unauthorized users to /403
</script>
```

## Current Permission Implementation

### Workspace Permissions

- **workspace.read**: Basic access to view workspace content
- **workspace.write**: Full management access including:
  - Create, edit, and disable workspace users
  - Update user permissions
  - Manage user names and details
  - View team member list

### Backend Integration

The permission system is fully integrated with the backend API:

- Team management endpoints require `workspace.write` permission
- Permissions are synced between the database and Keycloak
- User permissions can be dynamically updated through the team management interface
