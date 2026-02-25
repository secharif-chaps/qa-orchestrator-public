# Keycloak Organizations Migration - COMPLETED ✅

## Overview

**STATUS: MIGRATION COMPLETE**

This document outlines the completed migration from workspace-based multi-tenancy to Keycloak Organizations architecture.

**Key Changes:**
- ✅ Organizations are now managed in Keycloak, not in the application database
- ✅ All workspace tables and endpoints have been removed
- ✅ New `/organizations/*` endpoints replace `/workspace/*` endpoints
- ✅ User management happens through Keycloak Admin API
- ✅ Permission system uses organization-scoped roles

---

## Backend Changes (COMPLETED ✅)

### 1. Database Schema
- ✅ Added `organization_id` (UUID string) to companies, folders, tasks tables
- ✅ Removed `workspace_id` foreign keys
- ✅ Dropped all workspace tables (workspaces, workspace_members, user_workspace_permissions, workspace_modules)
- ✅ Created migration `002_drop_workspace_tables.py`

### 2. Organization Endpoints

#### GET /api/organizations/current
Returns current user's organization context from JWT token.

**Response:**
```json
{
  "id": "19226951-820a-4fc1-98eb-26010ac0890c",
  "name": "Chapsvision",
  "user_id": "fab2436d-fca9-4991-a47d-39d1a535fbe4",
  "username": "nmr"
}
```

#### GET /api/organizations/activities
Returns recent activities (companies and folders created by other users).

**Response:**
```json
[
  {
    "type": "company",
    "name": "Acme Corp",
    "owner": "john",
    "created_at": "2025-11-13T15:45:00Z"
  },
  {
    "type": "folder",
    "name": "Important Leads",
    "owner": "jane",
    "created_at": "2025-11-13T15:40:00Z"
  }
]
```

### 3. Team Management Endpoints

Team management now uses Keycloak Admin API through `/api/organizations/{organization_id}/users`:

**User Management:**
- `GET /api/organizations/{organization_id}/users` - List organization users
- `POST /api/organizations/{organization_id}/users` - Create user and add to organization
- `PUT /api/organizations/{organization_id}/users/{user_id}` - Update user
- `GET /api/organizations/{organization_id}/users/{user_id}` - Get user details

All endpoints require `organization.write` permission or `admin.organizations` role.

### 4. Files Deleted

**Models & Schemas:**
- ❌ `app/models/workspace.py` - Workspace models
- ❌ `app/schemas/workspace.py` - Workspace schemas
- ❌ `app/models/permission.py` - Database permission models
- ❌ `app/schemas/permission.py` - Permission schemas
- ❌ `app/schemas/workspace_user.py` - Workspace user schemas
- ❌ `app/schemas/admin_user.py` - Admin user schemas

**Services:**
- ❌ `app/services/workspace_user.py` - Workspace user service
- ❌ `app/services/permission.py` - Database permission service

**Core:**
- ❌ `app/core/workspace.py` - Old workspace context (replaced by `app/core/organization.py`)

### 5. Files Completely Rewritten

**Team Management:**
- ✅ `app/api/endpoints/team_management.py` - Completely rewritten to use Keycloak Admin API
  - Router prefix: `/workspaces` → `/organizations`
  - All database queries replaced with Keycloak API calls
  - Functions renamed to use "organization" terminology

**Schemas:**
- ✅ `app/schemas/team_management.py` - Renamed all schemas:
  - `WorkspaceUser` → `OrganizationUser`
  - `WorkspaceUserCreate` → `OrganizationUserCreate`
  - `WorkspaceUserUpdate` → `OrganizationUserUpdate`

### 6. Permission System Updates

**Role Renames:**
- `admin.workspaces` → `admin.organizations`
- `workspace.read` → `organization.read`
- `workspace.write` → `organization.write`

**Updated Files (58 references across 7 files):**
- `app/api/endpoints/admin.py` - All admin endpoints
- `app/api/endpoints/modules.py` - Module endpoints
- `app/api/endpoints/workspace.py` - Workspace endpoints (now deprecated)
- `app/core/workspace.py` - Workspace context utilities
- `app/services/permission.py` - Permission service

---

## Frontend Changes (TODO 🚧)

### Step 1: Update API Calls

Replace workspace API calls with organization calls:

#### Before:
```typescript
// Get current workspace
const { data } = await api.get('/workspace/current')
console.log(data.workspace_id) // Integer ID

// Get activities
const { data } = await api.get(`/workspace/${workspaceId}/activities`)
```

#### After:
```typescript
// Get current organization
const { data } = await api.get('/organizations/current')
console.log(data.id) // UUID string

// Get activities
const { data } = await api.get('/organizations/activities')
```

### Step 2: Update Data Models

#### Workspace Context (Before):
```typescript
interface WorkspaceContext {
  workspace_id: number
  workspace_name: string
  username: string
}
```

#### Organization Context (After):
```typescript
interface OrganizationContext {
  id: string  // UUID
  name: string
  user_id: string  // UUID
  username: string
}
```

### Step 3: Update Folder/Company Responses

All folder and company responses now return `organization_id` instead of `workspace_id`:

```typescript
interface FolderResponse {
  id: string
  organization_id: string  // Changed from workspace_id: number
  owner: string
  name: string
  // ... other fields
}
```

### Step 4: Remove Workspace Admin Features

The following admin features should be REMOVED from frontend:

1. **Workspace Management Page** (`/admin/workspaces`):
   - Create workspace
   - Edit workspace
   - Delete workspace
   - List all workspaces

2. **User Management in Workspace** (`/admin/workspaces/:id/users`):
   - Create user
   - Edit user
   - Delete user
   - Reset password
   - Assign to workspace

**Replacement:** Direct users to Keycloak Admin Console for organization and user management.

### Step 5: Update Navigation/UI Text

Replace all mentions of "workspace" with "organization":
- "Switch Workspace" → "Organization: ChapsVision"
- "Workspace Settings" → Remove (handled in Keycloak)
- "Workspace Members" → "Team" (uses `/organizations/{id}/users` endpoint)

### Step 6: Update Permission Checks

Organization-scoped permissions:
- `organization.read` - View organization content
- `organization.write` - Modify organization and manage team
- `admin.organizations` - Global organization administration

---

## Keycloak Configuration (REQUIRED 🔧)

### 1. Enable Organizations Feature

Ensure Keycloak Organizations feature is enabled (Keycloak 26.1.5+).

### 2. Create Organizations

1. Go to Keycloak Admin Console
2. Navigate to Organizations (top-level menu)
3. Create organizations (e.g., "ChapsVision")
4. Assign users to organizations

### 3. Update Realm Roles

Rename role in Keycloak realm:
- `admin.workspaces` → `admin.organizations`

**IMPORTANT**: This role rename must be done in Keycloak for the application to work correctly.

### 4. Configure Organization Scopes

Ensure the `organization` scope is included in JWT tokens:
1. Client → mint-front → Client Scopes
2. Add `organization` scope
3. Verify JWT tokens include organization claim

---

## Migration Checklist

### Backend (COMPLETED ✅)
- [x] Add organization_id columns to companies, folders, tasks
- [x] Remove workspace_id foreign keys
- [x] Create organization endpoints
- [x] Update all services to use organization_id
- [x] Fix folder schemas to use organization_id
- [x] Fix task endpoints to use organization context
- [x] Remove JWT debug logging
- [x] Delete all workspace files (models, schemas, services)
- [x] Rewrite team_management.py to use Keycloak Admin API
- [x] Update all permission checks (admin.workspaces → admin.organizations)
- [x] Update test files to use organization terminology
- [x] Remove all backward compatibility aliases
- [x] Create migration to drop workspace tables
- [x] Drop workspace tables from database

### Frontend (TODO 🚧)
- [ ] Update API client to use /organizations/* endpoints
- [ ] Update data models (workspace_id → organization_id)
- [ ] Remove workspace admin pages
- [ ] Remove user management pages
- [ ] Update navigation/UI text
- [ ] Update permission checks (admin.workspaces → admin.organizations)
- [ ] Test all features end-to-end

### Keycloak (REQUIRED 🔧)
- [ ] Enable Organizations feature
- [ ] Create organizations and assign users
- [ ] **Rename admin.workspaces role to admin.organizations** (CRITICAL)
- [ ] Configure organization scope in JWT

---

## Architecture Overview

### Before: Workspace-Based Multi-Tenancy
```
Database Tables:
├── workspaces (id, name, slug)
├── workspace_members (workspace_id FK, user_id, permissions)
├── workspace_modules (workspace_id FK, module settings)
├── user_workspace_permissions (user_id, workspace_id, permissions)
└── companies/folders (workspace_id FK)

Authentication: Keycloak JWT + Database permission checks
```

### After: Keycloak Organizations
```
Database Tables:
├── companies/folders (organization_id UUID string - NO FK)
└── (No organization or membership tables)

Keycloak:
├── Organizations (managed in Keycloak)
├── Organization Membership (managed in Keycloak)
├── Realm Roles (admin.organizations, organization.read, organization.write)
└── JWT includes organization claim

Authentication: Keycloak JWT-only (no database permission checks)
```

---

## Testing

### Test Organization Endpoints

```bash
# Get current organization
curl -H "Authorization: Bearer $TOKEN" http://localhost:8000/api/organizations/current

# Get organization activities
curl -H "Authorization: Bearer $TOKEN" http://localhost:8000/api/organizations/activities
```

### Expected JWT Token Structure

```json
{
  "sub": "fab2436d-fca9-4991-a47d-39d1a535fbe4",
  "preferred_username": "nmr",
  "organization": [
    {
      "Chapsvision": {
        "id": "19226951-820a-4fc1-98eb-26010ac0890c"
      }
    },
    "Chapsvision"
  ],
  "realm_access": {
    "roles": ["organization.read", "organization.write", "company.view"]
  }
}
```

---

## Support

For questions about this migration:
- Backend implementation: See `app/api/endpoints/organizations.py`
- Organization context: See `app/core/organization.py`
- Team management: See `app/api/endpoints/team_management.py`
- Keycloak Admin API: See `app/services/keycloak_admin.py`
- JWT structure: See Keycloak Organizations documentation
