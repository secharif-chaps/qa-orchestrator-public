# ADR-0005: Multi-Tenancy with Keycloak Organizations

## Status

**Status:** Accepted

**Date:** 2024-01-15

**Decision Makers:** ChapsMind Engineering Team

**Tags:** security, multi-tenancy, keycloak

---

## Context

ChapsMind serves multiple client organizations with strict data isolation requirements:

- Each client's data must be completely isolated from other clients
- Users belong to a single organization
- Permissions are scoped to an organization
- Billing and feature access are per-organization
- No cross-organization data leakage is acceptable

We need a multi-tenancy approach that:
- Provides strong isolation between tenants
- Integrates with our chosen identity provider (Keycloak)
- Scales without requiring separate database instances per tenant
- Supports organization-level feature flags and settings

Keycloak 24+ introduces the "Organizations" feature, providing built-in multi-tenancy support.

---

## Decision

We will use **Keycloak Organizations** as the multi-tenancy mechanism. Each client organization is represented as a Keycloak Organization, and all data queries are scoped by organization ID.

Key implementation decisions:

- **Keycloak Organizations**: Each client is a Keycloak Organization
- **Organization ID in JWT**: Organization membership included in access tokens
- **Database filtering**: All queries filtered by `organization_id` column
- **No separate databases**: Shared database with organization-scoped rows
- **No organization_members table**: Membership managed entirely in Keycloak

---

## Options Considered

### Option 1: Keycloak Organizations (Chosen)

**Description:** Use Keycloak's built-in Organizations feature for multi-tenancy.

**Pros:**
- Native integration with existing identity provider
- Organization membership managed in Keycloak
- Organization ID included in JWT tokens automatically
- Single source of truth for organization membership
- No synchronization needed between systems
- Built-in organization administration UI

**Cons:**
- Requires Keycloak 24+ (relatively new feature)
- Limited customization compared to custom implementation
- Keycloak becomes critical dependency

### Option 2: Custom Organization Tables

**Description:** Build custom `organizations` and `organization_members` tables in application database, synchronized with Keycloak.

**Pros:**
- Full control over organization data model
- Can add custom organization attributes
- SQL JOINs with organization data

**Cons:**
- Data synchronization complexity
- Potential for inconsistency
- Duplicate source of truth
- Additional maintenance burden
- Must handle membership lifecycle

### Option 3: Separate Database per Tenant

**Description:** Each organization gets its own database instance.

**Pros:**
- Strongest isolation guarantee
- Easy to meet compliance requirements
- Simplified data deletion per tenant

**Cons:**
- Significant infrastructure complexity
- Higher costs at scale
- Cross-tenant queries impossible
- Connection management complexity
- Operational overhead

### Option 4: Schema-per-Tenant

**Description:** Shared database with separate schema for each organization.

**Pros:**
- Good isolation within single database
- Easier backup/restore per tenant
- Some infrastructure simplification

**Cons:**
- Schema proliferation at scale
- Migration complexity (must apply to all schemas)
- Connection string management
- PostgreSQL limitations on schema count

---

## Consequences

### Positive

- **Single Source of Truth**: Organization membership only in Keycloak
- **Automatic Token Integration**: Organization ID in every JWT
- **Simplified Data Model**: No organization_members table needed
- **Strong Isolation**: All queries filtered by organization_id
- **Scalability**: Shared database scales with organization count
- **Reduced Code**: No membership CRUD operations in application

### Negative

- **Keycloak Dependency**: Organization features tied to Keycloak availability
- **Limited Customization**: Organization model defined by Keycloak
- **Query Complexity**: Must remember to filter by organization_id everywhere
- **No SQL JOINs**: Cannot join organization details in database queries

### Neutral

- Team must understand Keycloak Organizations feature
- Organization administration happens in Keycloak admin console
- Future features must maintain organization isolation pattern

---

## Implementation Notes

### Organization ID Extraction

Organization ID is extracted from JWT claims:

```python
# core/organization.py
from fastapi import Depends
from fastapi_keycloak import OIDCUser

class OrganizationContext:
    organization_id: str
    username: str

def get_user_organization(
    user: OIDCUser = Depends(idp.get_current_user())
) -> OrganizationContext:
    org_data = user.extra_fields.get("organization", {})
    return OrganizationContext(
        organization_id=org_data.get("id"),
        username=user.preferred_username
    )
```

### Database Schema Pattern

All organization-scoped tables include `organization_id`:

```sql
CREATE TABLE companies (
    id SERIAL PRIMARY KEY,
    name VARCHAR NOT NULL,
    organization_id VARCHAR(36) NOT NULL,  -- Keycloak org UUID
    -- ... other fields
);

CREATE INDEX idx_companies_org ON companies(organization_id);
```

### Query Pattern

Always filter by organization:

```python
@router.get("/companies")
def list_companies(
    org_context: OrganizationContext = Depends(get_user_organization),
    db: Session = Depends(get_db)
):
    return db.query(Company).filter(
        Company.organization_id == org_context.organization_id
    ).all()
```

---

## References

- [Keycloak Organizations Documentation](https://www.keycloak.org/docs/latest/server_admin/#_organizations)
- [ADR-0003: Keycloak Authentication](./0003-keycloak-authentication.md)
- [ADR-0007: User/Org Identification](./0007-keycloak-user-org-identification.md)
- [Constraints Document](../01-context/constraints.md)
