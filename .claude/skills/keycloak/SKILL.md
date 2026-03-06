---
name: keycloak
description: >
  Keycloak authentication, authorization, and organization-based multi-tenancy for ChapsMind.
  Use when implementing JWT validation, extracting organization context from tokens,
  checking role-based permissions, managing users via Keycloak Admin API, or configuring
  OIDC authentication on frontend. Activates when working on apps/screen/app/core/keycloak.py,
  apps/screen/app/core/organization.py, apps/screen/app/core/permissions.py,
  apps/screen/app/services/keycloak_admin.py, or apps/front/src/stores/auth.ts.
  CRITICAL - No user or organization tables in database; all identity data lives in Keycloak.
allowed-tools: Read, Write, Edit, Glob, Grep, Bash
metadata:
  author: chaps-e
  version: "1.0"
---

## When to use this skill

- When adding authentication to API endpoints
- When extracting organization context from JWT tokens
- When checking permissions (role-based access control)
- When managing users via Keycloak Admin API
- When configuring frontend OIDC authentication
- When implementing internal service-to-service JWT
- When working on multi-tenant data isolation

# Keycloak Authentication & Multi-Tenancy

**CRITICAL**: No `users` or `organizations` table in the database. All identity data lives in Keycloak. Database tables store only Keycloak UUIDs as VARCHAR fields (`owner_id`, `organization_id`).

## Backend Authentication

### Protecting Endpoints

```python
from app.core.keycloak import idp, OIDCUser
from app.core.organization import get_user_organization, OrganizationContext

@router.get("/companies")
async def list_companies(
    user: OIDCUser = Depends(idp.get_current_user(required_roles=["company.view"])),
    org_context: OrganizationContext = Depends(get_user_organization),
    db: Session = Depends(get_db),
) -> list[CompanyResponse]:
    # org_context.organization_id ensures tenant isolation
    companies = db.query(Company).filter(
        Company.organization_id == org_context.organization_id
    ).all()
    return companies
```

### Organization Context

```python
# apps/screen/app/core/organization.py
class OrganizationContext(BaseModel):
    organization_id: str      # Keycloak org UUID
    organization_name: str
    user_id: str              # JWT sub claim
    username: str
```

The organization is extracted from the JWT `organization` claim:

```json
["OrgName", { "OrgName": { "id": "uuid-here" } }]
```

## Permission Tiers

```python
# apps/screen/app/core/permissions.py
READER = ["organization.read"]
WRITER = ["organization.read", "organization.write", "company.view", "company.create", "company.delete"]
MANAGER = WRITER + ["organization.manage"]
ADMIN = MANAGER + ["admin.organizations"]
```

| Permission            | Scope  | Description                |
| --------------------- | ------ | -------------------------- |
| `organization.read`   | Org    | View organization content  |
| `organization.write`  | Org    | Manage org settings + team |
| `company.view`        | Org    | View companies             |
| `company.create`      | Org    | Search + create companies  |
| `company.delete`      | Org    | Delete companies           |
| `admin.organizations` | Global | Admin all organizations    |

## Frontend Authentication

### Auth Store (`apps/front/src/stores/auth.ts`)

```typescript
import { UserManager, WebStorageStateStore } from "oidc-client-ts";

const userManager = new UserManager({
  authority: `${keycloakUrl}/realms/${realm}`,
  client_id: clientId,
  redirect_uri: `${baseUrl}/auth/callback`,
  silent_redirect_uri: `${baseUrl}/auth/silent-callback`,
  response_type: "code",
  scope: "openid profile email",
  automaticSilentRenew: true,
});
```

### Token Parsing

```typescript
import { jwtDecode } from "jwt-decode";

const userRoles = computed(() => {
  const token = jwtDecode(user.value?.access_token);
  return token.realm_access?.roles || [];
});

const organizationId = computed(() => {
  const token = jwtDecode(user.value?.access_token);
  return token.organization_id || null;
});
```

### API Requests

```typescript
// apps/front/src/api/client.ts
headers["Authorization"] = `Bearer ${token}`;
```

## Keycloak Admin API

```python
# apps/screen/app/services/keycloak_admin.py
class KeycloakAdminService:
    async def create_user(self, user_data: dict) -> dict:
        """POST /admin/realms/{realm}/users"""
        ...

    async def search_users(self, search: str) -> list[dict]:
        """GET /admin/realms/{realm}/users?search=..."""
        ...

    async def get_user(self, user_id: str) -> dict | None:
        """GET /admin/realms/{realm}/users/{userId}"""
        ...
```

Admin token management uses client credentials grant with `admin-cli` client.

## Internal Service-to-Service JWT

```python
# apps/screen/app/core/internal_jwt.py
# Format: Authorization: Internal {token}
# HMAC-SHA256 (not RSA) with shared secret
# Optional IP allowlist validation
```

## Database Pattern

```python
# NO foreign keys to users or organizations
class Company(Base):
    owner_id: Mapped[str] = mapped_column(String(36))         # Keycloak user UUID
    owner_username: Mapped[str] = mapped_column(String(255))   # Denormalized for display
    organization_id: Mapped[str] = mapped_column(String(36))   # Keycloak org UUID
```

## Key Rules

1. **No user tables** - All identity in Keycloak, DB stores only UUIDs
2. **Always filter by organization_id** - Multi-tenancy at query level
3. **Use `required_roles`** - Declare permissions in endpoint dependencies
4. **OrganizationContext for every org-scoped endpoint** - Extract from JWT
5. **Admin API uses service account** - `admin-cli` client credentials
6. **Frontend uses oidc-client-ts** - Not keycloak-js (lighter, standard OIDC)
7. **Silent token renewal** - `automaticSilentRenew: true` + visibility change refresh

## Key Files

- `apps/screen/app/core/keycloak.py` - IDP init, OIDCUser
- `apps/screen/app/core/organization.py` - Org context extraction
- `apps/screen/app/core/permissions.py` - Permission tiers
- `apps/screen/app/core/internal_jwt.py` - Service-to-service JWT
- `apps/screen/app/services/keycloak_admin.py` - Admin API
- `apps/front/src/stores/auth.ts` - Frontend auth store
