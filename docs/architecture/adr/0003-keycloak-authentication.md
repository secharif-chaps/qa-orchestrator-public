# ADR-0003: Keycloak for Authentication

## Status

**Status:** Accepted

**Date:** 2024-01-15

**Decision Makers:** ChapsMind Engineering Team

**Tags:** security, authentication, identity, multi-tenancy

---

## Context

ChapsMind requires a robust authentication and identity management solution that can:

- Handle user authentication for a B2B SaaS application
- Support multi-tenancy with client isolation (organizations)
- Provide role-based access control (RBAC)
- Comply with GDPR requirements for user data storage
- Enable single sign-on (SSO) capabilities for enterprise customers
- Scale to support multiple Chapsvision modules/products
- Manage user lifecycle without storing PII in the application database

A critical requirement is **multi-tenancy**: each client organization must have isolated data and users, with the ability to manage their own team members within their organization.

---

## Decision

We will use **self-hosted Keycloak** as the identity and access management solution.

Key implementation decisions:

- Use Keycloak Organizations feature for multi-tenancy
- Store NO user data in application database (all identity in Keycloak)
- Use OIDC protocol for authentication
- Implement RBAC using Keycloak roles mapped to application permissions
- Use `fastapi-keycloak` for backend integration
- Use `oidc-client` for frontend integration
- Host Keycloak on our own infrastructure for full control

Permission model:

```
Organization Permissions:
- organization.read
- organization.write

Company Permissions:
- company.view
- company.create
- company.update
- company.delete

Admin Permissions:
- admin.organizations
```

---

## Options Considered

### Option 1: Self-Hosted Keycloak

**Description:** Open-source identity and access management solution, self-hosted on our infrastructure.

**Pros:**

- Full control over user data (GDPR compliance)
- Built-in Organizations feature for multi-tenancy
- No per-user pricing
- Extensive customization options
- Enterprise-ready with LDAP/AD integration
- SSO support for enterprise customers
- Active development and large community
- Can be shared across multiple Chapsvision products

**Cons:**

- Requires infrastructure management and maintenance
- Higher initial setup complexity
- Team must maintain updates and security patches
- Learning curve for Keycloak administration
- Resource overhead (Java-based, memory intensive)

### Option 2: Auth0

**Description:** Cloud-based identity platform with comprehensive features.

**Pros:**

- Fully managed service (no infrastructure)
- Excellent documentation and SDKs
- Quick to implement
- Built-in social login providers
- Good developer experience

**Cons:**

- Per-user pricing becomes expensive at scale
- User data stored in Auth0's cloud (GDPR considerations)
- Less control over customization
- Limited multi-tenancy support (requires Enterprise plan)
- Vendor lock-in concerns
- Cannot share across Chapsvision products cost-effectively

### Option 3: AWS Cognito

**Description:** Amazon's identity service integrated with AWS ecosystem.

**Pros:**

- Tight AWS integration
- Pay-per-use pricing
- Managed service
- Scales automatically

**Cons:**

- Limited customization options
- No native Organizations/multi-tenancy feature
- User data in AWS (may complicate GDPR)
- Less flexible than Keycloak
- AWS-specific (limits future cloud choices)
- Would require custom multi-tenancy implementation

---

## Consequences

### Positive

- **Data Sovereignty**: All user data stored on our infrastructure, simplifying GDPR compliance
- **Multi-Tenancy Native**: Keycloak Organizations provides built-in client isolation without custom code
- **Cost Effective**: No per-user fees, predictable infrastructure costs
- **Full Control**: Complete customization of authentication flows, themes, and policies
- **Enterprise Ready**: LDAP/AD integration and SSO support for enterprise customers
- **Platform Foundation**: Can serve as identity provider for all Chapsvision modules
- **No PII in App DB**: Application database never stores personal user information

### Negative

- **Operational Overhead**: Team must manage Keycloak upgrades, backups, and security
- **Initial Complexity**: Setup and configuration more complex than managed services
- **Resource Requirements**: Keycloak requires dedicated compute resources (Java-based)
- **Expertise Required**: Team needs Keycloak administration knowledge

### Neutral

- Self-hosting provides maximum flexibility but requires corresponding responsibility
- The decision to store no users in the application database simplifies the app but creates dependency on Keycloak availability

---

## Implementation Notes

### Database Architecture

Due to this decision, the application database:

- Has NO `users` table
- Has NO `organization_members` table
- Stores Keycloak user IDs as `owner_id` VARCHAR fields
- Stores organization IDs from Keycloak as `organization_id` VARCHAR fields

### Authentication Flow

```
1. User navigates to protected route
2. Frontend redirects to Keycloak login
3. User authenticates with Keycloak
4. Keycloak returns tokens (access + refresh)
5. Frontend stores tokens, includes in API requests
6. Backend validates JWT with Keycloak public key
7. Backend extracts user ID, org ID, roles from token
```

### Integration Libraries

- **Backend**: `fastapi-keycloak` (1.1.1), `python-keycloak` (^3.9.1)
- **Frontend**: `oidc-client` (1.11.5)

---

## References

- [Keycloak Documentation](https://www.keycloak.org/documentation)
- [Keycloak Organizations](https://www.keycloak.org/docs/latest/server_admin/#_organizations)
- [OIDC Protocol](https://openid.net/connect/)
- [ChapsMind Security Architecture](../05-security/)
- [GDPR Compliance Guide](https://gdpr.eu/)
