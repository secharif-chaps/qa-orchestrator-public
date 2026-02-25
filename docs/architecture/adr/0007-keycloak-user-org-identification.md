# ADR-0007: User and Organization Identity in Keycloak

## Status

**Status:** Accepted

**Date:** 2024-01-15

**Decision Makers:** ChapsMind Engineering Team

**Tags:** security, architecture, database, identity

---

## Context

ChapsMind needs to reference users and organizations throughout the application (ownership, audit trails, access control) while:

- Avoiding data duplication between the identity provider and application database
- Maintaining GDPR compliance by minimizing PII storage
- Ensuring consistency between authentication state and application state
- Keeping the application database schema simple and focused on business data
- Supporting the multi-tenancy architecture established in ADR-0005

The system already uses Keycloak for authentication (ADR-0003) and Keycloak Organizations for multi-tenancy (ADR-0005). The question is: should the application maintain its own user and organization tables, or rely entirely on Keycloak as the source of truth?

Traditional approaches would create `users` and `organization_members` tables synchronized with Keycloak, but this introduces:
- Data synchronization complexity
- Potential for data inconsistency
- GDPR compliance challenges with PII in multiple locations
- Additional maintenance burden

---

## Decision

We will **NOT** store users or organization membership in the application database. All identity data resides exclusively in Keycloak.

Key implementation decisions:

- **No `users` table**: The application database has no table storing user information
- **No `organization_members` table**: Organization membership is managed entirely in Keycloak Organizations
- **No `organizations` table for identity**: Organization identity data lives in Keycloak (Note: a separate `organizations` table exists for application-specific data like token balances, but contains no identity PII)
- **Reference by ID only**: Database tables store Keycloak UUIDs as simple VARCHAR/String fields
- **Denormalized display names**: Where needed for display (e.g., `owner_username`), store as denormalized fields updated on write
- **JWT as source**: User ID, organization ID, and roles are extracted from JWT tokens on each request

Database reference pattern:
```sql
-- Tables reference Keycloak IDs without foreign keys
CREATE TABLE companies (
    id SERIAL PRIMARY KEY,
    name VARCHAR NOT NULL,
    organization_id VARCHAR(36) NOT NULL,  -- Keycloak organization UUID
    owner_id VARCHAR(36) NOT NULL,          -- Keycloak user UUID
    owner_username VARCHAR(255),            -- Denormalized for display
    -- ... business data fields
);

CREATE TABLE folders (
    id SERIAL PRIMARY KEY,
    name VARCHAR NOT NULL,
    organization_id VARCHAR(36) NOT NULL,  -- Keycloak organization UUID
    owner_id VARCHAR(36) NOT NULL,          -- Keycloak user UUID
    -- ... business data fields
);
```

---

## Options Considered

### Option 1: Identity in Keycloak Only (Chosen)

**Description:** Store no user or organization identity data in the application database. Reference Keycloak UUIDs as simple string fields. Extract all identity information from JWT tokens at request time.

**Pros:**
- Single source of truth for identity data (Keycloak)
- No data synchronization required
- GDPR-compliant: PII stored only in Keycloak, not scattered across databases
- Simplified database schema focused on business data
- No user lifecycle management code in application
- Automatic consistency: if user exists in token, they exist in system
- Reduced maintenance burden

**Cons:**
- Cannot perform SQL JOINs with user data
- Need Keycloak API calls to fetch user details (names, emails)
- Requires denormalization for display names
- Keycloak becomes critical dependency for user metadata

### Option 2: Synchronized User Tables

**Description:** Maintain `users` and `organization_members` tables that are synchronized with Keycloak through webhooks or periodic sync jobs.

**Pros:**
- Can JOIN user data in SQL queries
- User data available even if Keycloak is temporarily unavailable
- Familiar pattern for developers

**Cons:**
- Synchronization complexity and failure modes
- Data inconsistency risk between Keycloak and database
- GDPR complexity: PII in multiple locations
- Additional infrastructure (webhooks, sync jobs)
- Must handle user lifecycle events (create, update, delete)
- Maintenance burden for sync code

### Option 3: Hybrid Approach

**Description:** Store minimal user references (ID, username, email) in application database, updated on each login.

**Pros:**
- Basic user data available for queries
- Updated naturally through login flow
- Simpler than full synchronization

**Cons:**
- Stale data for users who haven't logged in recently
- Still duplicates some PII (emails, usernames)
- Inconsistent data between logged-in and not-logged-in users
- Doesn't solve organization membership synchronization
- Unclear source of truth

---

## Consequences

### Positive

- **Single Source of Truth**: All identity data lives in Keycloak, eliminating synchronization issues
- **GDPR Compliance**: No PII in application database; user data deletion only requires Keycloak
- **Simplified Schema**: Database contains only business data, not identity management
- **No Sync Code**: No webhooks, cron jobs, or event handlers for user synchronization
- **Automatic Consistency**: JWT validation ensures referenced users are valid
- **Cleaner Architecture**: Clear separation between identity (Keycloak) and business data (PostgreSQL)
- **Reduced Maintenance**: No user CRUD operations in application codebase

### Negative

- **Query Limitations**: Cannot JOIN user details in SQL; must make separate Keycloak API calls
- **Keycloak Dependency**: User metadata requires Keycloak availability
- **Denormalization Overhead**: Display fields like `owner_username` must be stored and potentially updated
- **Performance Consideration**: Fetching user lists requires Keycloak API calls instead of database queries

### Neutral

- This decision deepens the Keycloak dependency established in ADR-0003 and ADR-0005
- Team must understand that user queries go to Keycloak API, not application database
- Some reporting features may require caching user data for performance

---

## Implementation Notes

### Backend Patterns

**Extracting identity from JWT:**
```python
# core/organization.py
from fastapi import Depends
from fastapi_keycloak import OIDCUser

async def get_current_user(
    user: OIDCUser = Depends(idp.get_current_user())
) -> UserContext:
    return UserContext(
        user_id=user.sub,
        username=user.preferred_username,
        organization_id=user.extra_fields.get("organization", {}).get("id"),
        roles=user.realm_access.roles
    )
```

**Saving references:**
```python
# services/companies.py
async def create_company(
    db: AsyncSession,
    data: CompanyCreate,
    user: UserContext
) -> Company:
    company = Company(
        name=data.name,
        organization_id=user.organization_id,  # From JWT
        owner_id=user.user_id,                  # From JWT
        owner_username=user.username,           # Denormalized for display
    )
    db.add(company)
    await db.commit()
    return company
```

**Fetching user details (when needed):**
```python
# services/keycloak.py
from python_keycloak import KeycloakAdmin

async def get_organization_members(org_id: str) -> list[UserInfo]:
    """Fetch organization members from Keycloak API."""
    admin = KeycloakAdmin(...)
    members = admin.get_organization_members(org_id)
    return [UserInfo(id=m["id"], username=m["username"]) for m in members]
```

### Frontend Patterns

**Auth store with user identity:**
```typescript
// stores/auth.ts
export const useAuthStore = defineStore('auth', () => {
  const user = ref<User | null>(null)

  // User data comes from Keycloak token, not API
  const userId = computed(() => user.value?.sub)
  const username = computed(() => user.value?.preferred_username)
  const organizationId = computed(() => user.value?.organization?.id)
})
```

### Tables That Reference Identity

Current tables with Keycloak ID references:
- `companies.owner_id`, `companies.organization_id`
- `folders.owner_id`, `folders.organization_id`
- `folder_shares.shared_with_user_id`
- `token_transactions.created_by`
- `user_folder_favorites.user_id`

None of these have foreign key constraints to user/organization tables (because those tables don't exist).

---

## References

- [ADR-0003: Keycloak Authentication](./0003-keycloak-authentication.md)
- [ADR-0005: Multi-Tenancy with Keycloak Organizations](./0005-multi-tenancy-keycloak-organizations.md)
- [Keycloak Admin REST API](https://www.keycloak.org/docs-api/latest/rest-api/)
- [GDPR Data Minimization Principle](https://gdpr.eu/article-5-how-to-process-personal-data/)
- [ChapsMind Database Guidelines](../../../CLAUDE.md#database-schema-guidelines)
