# Constraints

This document outlines the business, legal, and technical constraints that shape the ChapsMind architecture.

---

## Business Constraints

### Parent Company Relationship

| Constraint | Description |
|------------|-------------|
| **Parent Organization** | Chapsvision (market intelligence software catalog) |
| **Strategic Direction** | Rework existing Chapsvision products into ChapsMind modules |
| **Brand Alignment** | ChapsMind must align with Chapsvision's market positioning |

**Implications:**
- Must support migration of existing Chapsvision product features (Target, Explore, Scan)
- Architecture should enable gradual feature migration without disruption
- Shared infrastructure and authentication may be required

### Modular Sales Model

| Constraint | Description |
|------------|-------------|
| **Sales Approach** | Modules sold independently or as bundles |
| **Pricing Model** | Per-module licensing with synergy discounts and shared token system |
| **Client Flexibility** | Clients can start with one module and expand |

**Implications:**
- Each module must function independently
- Modules must integrate seamlessly when combined
- Feature flags and entitlements required per organization
- Licensing and access control at module level

### Multi-Tenant Architecture

| Constraint | Description |
|------------|-------------|
| **Tenant Model** | Single application, multiple client organizations |
| **Data Isolation** | Complete separation between organizations |
| **Customization** | Organization-specific settings and configurations |

**Implications:**
- All data queries must be organization-scoped
- No cross-organization data leakage
- Organization-level feature toggles and settings
- Per-organization API integrations (future)

---

## Legal Constraints

### GDPR Compliance

| Constraint | Description |
|------------|-------------|
| **Regulation** | General Data Protection Regulation (EU) |
| **Applicability** | All users in the European Union |
| **Key Requirements** | Data protection, consent, right to erasure |

**Implications:**

1. **Data Storage Location**
   - User identity data stored in Keycloak (not application database)
   - Keycloak configured for GDPR-compliant data handling
   - No personal data in application database user tables (there are none)

2. **Right to Erasure**
   - Keycloak handles user deletion
   - Application data must be deletable on user request
   - Audit logs must be managed appropriately

3. **Data Processing**
   - Clear consent mechanisms for data collection
   - Transparent about AI processing of company data
   - Data processing agreements with third-party services

4. **Data Minimization**
   - Only collect necessary user data
   - Company data is publicly available information
   - User preferences and settings kept minimal

### Data Source Compliance

| Constraint | Description |
|------------|-------------|
| **Data Sources** | Publicly available company information |
| **Web Scraping** | Must comply with terms of service |
| **API Usage** | Licensed data sources (Pappers, etc.) |

**Implications:**
- Respect robots.txt and rate limiting
- Document data source licenses and agreements
- Clear attribution of data sources where required

---

## Technical Constraints

### Keycloak Organizations for Multi-Tenancy

| Constraint | Description |
|------------|-------------|
| **Identity Provider** | Keycloak (self-hosted) |
| **Multi-Tenancy** | Keycloak Organizations feature |
| **Version Requirement** | Keycloak 24+ for Organizations support |

**Implications:**

1. **No Users Table in Database**
   - All user identity managed by Keycloak
   - User references stored as Keycloak user IDs (UUIDs)
   - Username stored denormalized for display purposes only

2. **No Organizations Table in Database**
   - Organization membership managed in Keycloak
   - Organization IDs from Keycloak used in all data
   - Organization settings may be stored in application DB

3. **Permission Model**
   - Permissions derived from Keycloak roles
   - Resource-based permission format: `resource.action`
   - Route and component-level permission checks

4. **Session Management**
   - OIDC tokens for authentication
   - Token refresh handled by frontend
   - JWT validation in backend

### AI Orchestration Platform

| Constraint | Description |
|------------|-------------|
| **LLM Orchestration** | Dify platform |
| **Integration** | REST API with workflow-specific API keys |
| **Callbacks** | Dify callbacks to backend with results |

**Implications:**
- Dify API must be accessible from backend
- Each workflow has its own API key
- Dify calls back to backend with results
- Async task processing via Celery
- Error handling for AI service failures

### Message Queue and Task Processing

| Constraint | Description |
|------------|-------------|
| **Message Broker** | RabbitMQ |
| **Task Queue** | Celery |
| **Monitoring** | Celery Flower |

**Implications:**
- No Redis in current stack
- RabbitMQ for all async communication
- Task status tracking in database
- Worker scaling for task processing

### Frontend Framework

| Constraint | Description |
|------------|-------------|
| **Framework** | Vue.js 3 with Composition API |
| **Language** | TypeScript (strict) |
| **State Management** | Pinia + Pinia Colada |
| **Routing** | File-based (unplugin-vue-router) |

**Implications:**
- No Options API usage
- Type-safe development required
- Query caching on frontend only
- SSR migration planned (Nuxt.js)

### Backend Framework

| Constraint | Description |
|------------|-------------|
| **Framework** | FastAPI |
| **ORM** | SQLAlchemy |
| **Validation** | Pydantic schemas |
| **Migrations** | Alembic |

**Implications:**
- Async endpoint support
- Auto-generated OpenAPI documentation
- Type hints for all code
- Service layer pattern

---

## Constraint Summary Matrix

| Category | Constraint | Impact Level | Mitigation |
|----------|------------|--------------|------------|
| Business | Parent company direction | High | Modular architecture |
| Business | Modular sales | High | Feature isolation |
| Business | Multi-tenancy | High | Organization-scoped data |
| Legal | GDPR compliance | High | Keycloak for user data |
| Legal | Data source compliance | Medium | Licensed sources |
| Technical | Keycloak Organizations | High | No DB user/org tables |
| Technical | Dify workflows | Medium | Async processing |
| Technical | RabbitMQ + Celery | Medium | Task queue patterns |

---

## Related Documents

- [Context README](./README.md) - Project overview and roadmap
- [Stakeholders](./stakeholders.md) - Primary users and their needs
- [ADR: Multi-Tenancy with Keycloak Organizations](../adr/0005-multi-tenancy-keycloak-organizations.md)
- [ADR: Keycloak User/Org Identification](../adr/0007-keycloak-user-org-identification.md)
