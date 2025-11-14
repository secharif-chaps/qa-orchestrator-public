# Keycloak Organizations Migration Guide

## Overview

This document outlines the migration from workspace-based multi-tenancy to Keycloak Organizations.

**Key Changes:**
- Organizations are now managed in Keycloak, not in the application database
- Workspace tables and endpoints will be removed
- New `/organization/*` endpoints replace `/workspace/*` endpoints
- User management happens in Keycloak Admin Console

---

## Backend Changes (COMPLETED ✅)

### 1. Database Schema
- ✅ Added `organization_id` (UUID string) to companies, folders, tasks tables
- ✅ Removed `workspace_id` foreign keys
- ✅ Removed workspace foreign key constraints
- ✅ Truncated all data (dev environment only)

### 2. New Organization Endpoints

#### GET /api/organization/current
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

**Replaces:** `GET /api/workspace/current`

#### GET /api/organization/activities
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

**Replaces:** `GET /api/workspace/{workspace_id}/activities`

### 3. Deprecated Endpoints (Will be removed after frontend migration)

The following workspace endpoints are marked as deprecated and should NOT be used:

**User Management (Now handled in Keycloak):**
- `POST /api/workspace/admin/{workspace_id}/users` - Create user
- `PUT /api/workspace/admin/{workspace_id}/users/{user_id}` - Update user
- `DELETE /api/workspace/admin/{workspace_id}/users/{user_id}` - Delete user
- `PATCH /api/workspace/admin/{workspace_id}/users/{user_id}/status` - Change status

**Workspace Management (Now handled in Keycloak):**
- `GET /api/workspace/admin/all` - List all workspaces
- `POST /api/workspace/admin` - Create workspace
- `PUT /api/workspace/admin/{workspace_id}` - Update workspace
- `DELETE /api/workspace/admin/{workspace_id}` - Delete workspace

**Membership Management (Now handled in Keycloak):**
- `GET /api/workspace/current/members` - List members
- `POST /api/workspace/current/members` - Add member
- `DELETE /api/workspace/current/members/{member_user_id}` - Remove member

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
const { data } = await api.get('/organization/current')
console.log(data.id) // UUID string

// Get activities
const { data } = await api.get('/organization/activities')
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
- "Workspace Members" → Remove (handled in Keycloak)

### Step 6: Update Permission Checks

Workspace-scoped permissions are now organization-scoped:
- `workspace.read` - Still valid (organization-scoped)
- `workspace.write` - Still valid (organization-scoped)
- `admin.workspaces` → `admin.organizations`

---

## Keycloak Configuration (TODO 🔧)

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

### Frontend (TODO 🚧)
- [ ] Update API client to use /organization/* endpoints
- [ ] Update data models (workspace_id → organization_id)
- [ ] Remove workspace admin pages
- [ ] Remove user management pages
- [ ] Update navigation/UI text
- [ ] Update permission checks (admin.workspaces → admin.organizations)
- [ ] Test all features end-to-end

### Keycloak (TODO 🔧)
- [ ] Enable Organizations feature
- [ ] Create organizations and assign users
- [ ] Rename admin.workspaces role to admin.organizations
- [ ] Configure organization scope in JWT

### Database Cleanup (After frontend migration)
- [ ] Drop workspace tables (workspaces, workspace_members, user_workspace_permissions, workspace_modules)
- [ ] Remove workspace endpoints file
- [ ] Remove workspace models and schemas
- [ ] Remove workspace services

---

## Testing

### Test Organization Endpoints

```bash
# Get current organization
curl -H "Authorization: Bearer $TOKEN" http://localhost:8000/api/organization/current

# Get organization activities
curl -H "Authorization: Bearer $TOKEN" http://localhost:8000/api/organization/activities
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
  ]
}
```

---

## Rollback Plan

If issues occur, the old workspace endpoints are still available (marked as deprecated).

To rollback:
1. Revert frontend to use /workspace/* endpoints
2. Keep using workspace tables (not yet dropped)
3. Debug issues in organization implementation

**Note:** Do NOT drop workspace tables until frontend migration is complete and tested!

---

## Support

For questions about this migration:
- Backend changes: See `app/api/endpoints/organization.py`
- Organization context: See `app/core/organization.py`
- JWT structure: See Keycloak Organizations documentation
