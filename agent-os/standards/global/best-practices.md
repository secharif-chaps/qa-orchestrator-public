# Development Best Practices

## Architecture Patterns

### Frontend Architecture (Vue 3)

**Composition API**: ALWAYS use `<script setup lang="ts">`, NEVER Options API

**State Management**:
- Pinia stores for global state (auth, organization, settings)
- Pinia Colada for server state (queries and mutations)
- Local refs/reactive for component state

**Data Fetching Pattern**:
```
API Functions (src/api/) → Queries (src/queries/) → Components
                         ↓
                    Mutations (src/mutations/)
```

**Component Organization**:
- Vuellar components from `@owlint/feathers-vue` for all UI
- Custom components only when Vuellar doesn't meet the need
- Feature-specific components in `src/components/features/`

### Backend Architecture (FastAPI + Python)

**Layer Separation**:
- **Models** (`app/models/`): SQLAlchemy database models
- **Schemas** (`app/schemas/`): Pydantic validation schemas
- **Services** (`app/services/`): Business logic
- **Endpoints** (`app/api/endpoints/`): FastAPI route handlers

**Dependency Injection**: Use FastAPI's `Depends()` for services, auth, organization context

**Service Pattern**:
- Services encapsulate business logic
- Accept dependencies via constructor
- Return typed results

**Security First**:
- Verify permissions BEFORE executing logic
- Validate and sanitize all user input
- Use organization context for multi-tenancy
- Return appropriate HTTP status codes

---

## Multi-Tenancy Pattern

### Organization-Based

All resources scoped to organization via Keycloak Organizations.

### User References

- **Username strings (VARCHAR)**, NOT foreign keys
- **No `users` table** in database
- User data stored in Keycloak
- Example: `companies.owner_username`, `organization_members.username`

### Organization Context

Injected via dependency, contains:
- `organization_id`: Current organization
- `username`: Current user
- `permissions`: User's permissions in organization
- `has_permission(perm)`: Permission check method

---

## Permission System

### Permission Format

Resource-based with format `resource.action`:
- **Company**: `company.view`, `company.create`, `company.delete`
- **Organization**: `organization.read`, `organization.write`
- **Admin**: `admin.organizations`

### Implementation

**Route-level** (Vue):
```vue
<route lang="yaml">
meta:
  permissions:
    - admin.organizations
    - organization.write
  requiresAuth: true
</route>
```

**Component-level** (Vue):
```vue
<Button v-if="canCreateCompany" label="Create" />
```

**Backend**:
```python
verify_company_permission(organization_context, "company.create")
```

### Before Adding Permissions

1. Check if existing permission covers the feature
2. Only create NEW permissions if existing ones don't fit
3. User MUST decide on permission choice before implementation

---

## Data Fetching Best Practices

### API Layer Pattern (Frontend)

1. **API Functions** (`src/api/`): Pure functions for HTTP calls
2. **Query Definitions** (`src/queries/`): Data fetching with Pinia Colada
3. **Mutations** (`src/mutations/`): Data modification
4. **Component Usage**: Use `useQuery` with reactive parameters

### Backend API Design

**HTTP Methods**:
- GET: Retrieve data
- POST: Create resource
- PUT: Full update
- PATCH: Partial update
- DELETE: Remove resource

**Status Codes**:
- 200: Success
- 201: Created
- 204: No Content
- 400: Bad Request
- 403: Forbidden
- 404: Not Found
- 422: Unprocessable Entity

**Pagination Pattern**:
```python
class PaginatedResponse(BaseModel, Generic[T]):
    items: list[T]
    total: int
    page: int
    per_page: int
    total_pages: int
```

---

## Security Best Practices

### Input Validation

- **ALWAYS validate and sanitize user input**
- Use Pydantic validators (`@field_validator`)
- Strip dangerous characters: `<`, `>`, `&`, `"`, `'`
- Validate length constraints
- Check for XSS patterns in JSON fields

### Permission Verification

**Backend**:
```python
# Check permissions BEFORE executing business logic
verify_company_permission(organization_context, "company.create")
verify_company_organization_access(company, organization_context)
```

**Frontend**:
```vue
<Button v-if="canCreateCompany">Create</Button>
<Alert v-if="!canCreateCompany" variant="info" title="Read-only access" />
```

### Authentication

- JWT tokens validated via Keycloak
- User context injected via dependencies
- No sensitive data in database (stored in Keycloak only)

### Database Security

- **No users table**: User data in Keycloak
- **Username references**: Use VARCHAR username strings
- **Soft delete**: Use `is_deleted` flag
- **Organization isolation**: Always filter by `organization_id`

---

## Dependencies

### Choose Libraries Wisely

When adding third-party dependencies:
- Select the most popular and actively maintained option
- Check GitHub for recent commits, issue resolution, stars
- Prefer libraries with TypeScript support (frontend)
- Check compatibility with current stack versions

### Prefer Vuellar Components

**ALWAYS use Vuellar components** when available:
- `Button`, `Input`, `Textarea`, `Select` for forms
- `Alert`, `Modal` for feedback
- `Table`, `Pagination` for data display
- `Tag`, `Badge`, `Chips` for labels

Only create custom components when Vuellar doesn't meet the need.

---

## Testing Best Practices

### Test Organization
- Keep tests alongside code: `Button.vue` + `Button.spec.ts`
- Use descriptive test names explaining behavior
- Test business logic, not implementation details

### Frontend Testing
- **Unit Tests**: Test composables, utilities, pure functions
- **Component Tests**: Test UI components in isolation
- **E2E Tests**: Test user workflows with Playwright

### Backend Testing
- **Unit Tests**: Test services, validators, utilities
- **Integration Tests**: Test API endpoints with pytest
- **Permission Tests**: Verify authorization logic

### Test Users

| User | Password | Permissions |
|------|----------|-------------|
| admin | admin123 | All permissions |
| company_manager | manager123 | company.* |
| company_viewer | viewer123 | company.view |
| team_manager | teammanager123 | organization.* |

---

## Accessibility (WCAG)

### Minimum Requirements

- **Contrast Ratios**: 7:1 for normal text, 4.5:1 for large text
- **Semantic HTML**: Use proper elements (nav, main, button)
- **ARIA Labels**: Label all interactive elements
- **Keyboard Navigation**: All functionality via keyboard
- **Focus Indicators**: Visible and compliant

### Vuellar Accessibility

Vuellar components are built with accessibility in mind:
- Proper ARIA attributes
- Keyboard navigation support
- Focus management
- Screen reader support

---

## Logging Best Practices

### Log Levels

| Level | Usage |
|-------|-------|
| DEBUG | Detailed diagnostic info |
| INFO | General informational |
| WARNING | Unexpected but non-blocking |
| ERROR | Error requiring attention |
| CRITICAL | System failure |

### Logging Pattern

```python
from app.core.logging_config import get_logger

logger = get_logger(__name__)

logger.info(
    "Processing company",
    extra={
        "company_id": company_id,
        "organization_id": organization_id,
        "user": username
    }
)
```

### Rules

- ✅ Include relevant IDs in `extra`
- ✅ Use `exc_info=True` for exceptions
- ❌ NEVER log sensitive data (passwords, tokens, PII)
