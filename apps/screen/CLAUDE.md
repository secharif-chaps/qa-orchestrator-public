# MINT Backend - Python Development Guide

## 📁 Project Structure

```
mint-server/
├── app/
│   ├── api/
│   │   └── endpoints/           # API route handlers
│   ├── core/                    # Core functionality
│   │   ├── exceptions.py        # Custom exception hierarchy
│   │   ├── logging_config.py    # Logging configuration
│   │   ├── security.py          # Security utilities
│   │   └── config.py            # Application settings
│   ├── models/                  # SQLAlchemy models
│   ├── services/                # Business logic layer
│   ├── infrastructure/          # External service clients (Dify, etc.)
│   ├── workers/                 # Celery tasks
│   └── main.py                  # FastAPI application entry point
├── alembic/                     # Database migrations
├── tests/                       # Test suite
├── pyproject.toml              # Poetry dependencies
└── CLAUDE.md                   # This file
```

---

## 🐍 Python Backend Development

### Code Quality Standards

#### 1. Exception Handling

**ALWAYS** use custom exceptions from `app/core/exceptions.py`:

```python
from app.core.exceptions import (
    AuthorizationError,
    ResourceNotFoundError,
    DatabaseError,
    ExternalServiceError
)
```

❌ **DON'T** use bare `except Exception`:
```python
# BAD
try:
    result = do_something()
except Exception as e:
    print(f"Error: {e}")  # Too broad, loses context
    raise
```

✅ **DO** use specific exceptions with context:
```python
# GOOD
from app.core.exceptions import DatabaseError, ExternalServiceError
from app.core.logging_config import get_logger

logger = get_logger(__name__)

try:
    result = do_something()
except sqlalchemy.exc.SQLAlchemyError as e:
    logger.error("Database error", exc_info=e, extra={"operation": "do_something"})
    raise DatabaseError("Failed to retrieve data", details={"error": str(e)}) from e
except requests.exceptions.RequestException as e:
    logger.error("External service error", exc_info=e)
    raise ExternalServiceError("Failed to call external API", details={"error": str(e)}) from e
```

#### 2. Logging

**ALWAYS** use `get_logger(__name__)` instead of `print()`:

```python
from app.core.logging_config import get_logger

logger = get_logger(__name__)

# Use structured logging with extra context
logger.info("Processing company", extra={"company_id": company_id, "organization_id": organization_id})
logger.warning("Rate limit approaching", extra={"user": username, "requests": count})
logger.error("Failed to connect to Dify", exc_info=True, extra={"workflow_id": wf_id})
```

**Log Levels**:
- `DEBUG`: Detailed diagnostic information (loop iterations, variable values)
- `INFO`: General informational messages (request started, task completed)
- `WARNING`: Unexpected events that don't prevent operation (retry attempt, deprecated usage)
- `ERROR`: Error conditions that need attention (failed operation, exception caught)
- `CRITICAL`: Critical conditions that may cause system failure (database down, Keycloak unreachable)

**Best Practices**:
- Include relevant IDs in `extra` dict for traceability
- Never log sensitive data (passwords, tokens, PII)
- Use `exc_info=True` when logging exceptions to include stack trace
- Keep log messages concise and actionable

#### 3. Type Hints

**ALL functions MUST have type hints**:

```python
# Use Python 3.10+ union syntax with |
def get_company_by_id(company_id: int, organization_id: str) -> Company | None:
    """Retrieve company by ID within organization.

    Args:
        company_id: Company primary key
        organization_id: Organization UUID for authorization check

    Returns:
        Company instance if found, None otherwise

    Raises:
        AuthorizationError: If company not in specified organization
    """
    company = db.query(Company).filter(Company.id == company_id).first()
    if company and company.organization_id != organization_id:
        raise AuthorizationError("Company not in organization")
    return company
```

**Type Hint Guidelines**:
- Use `|` for unions (not `Union` from typing)
- Use `list[str]` not `List[str]` (built-in generics)
- Use `dict[str, Any]` not `Dict[str, Any]`
- Use `None` explicitly in return types when function can return None

#### 4. Docstrings

**Use Google-style docstrings** for all public functions:

```python
def create_company(data: CompanyCreate, user_id: str, username: str, organization_id: str) -> Company:
    """Create a new company in the specified organization.

    This function creates a company record and triggers initial data collection
    tasks via Dify workflows.

    Args:
        data: Company creation data from API request
        user_id: Keycloak user UUID (from JWT sub claim)
        username: Username of the creating user (from JWT)
        organization_id: Target organization UUID (from JWT organization claim)

    Returns:
        Newly created Company instance with relationships loaded

    Raises:
        ValidationError: If company name or website is invalid
        AuthorizationError: If user lacks company.create permission
        DatabaseError: If database operation fails

    Example:
        company = create_company(
            data=CompanyCreate(name="Acme Corp", website="acme.com"),
            user_id="user-uuid-123",
            username="john@example.com",
            organization_id="org-uuid-456"
        )
    """
    # Implementation here
```

#### 5. Formatting & Linting

**Use Ruff** for linting and formatting:

```bash
# From mint-server directory
ruff check .          # Check for issues
ruff check --fix .    # Auto-fix issues
ruff format .         # Format code
```

**No custom Ruff configuration needed** - use sensible defaults.

If you need to ignore specific rules for a file:
```python
# ruff: noqa: E501  # Ignore line length for this file
```

---

## 🏗️ Architecture Patterns

### Service Layer Pattern

Business logic belongs in `app/services/`, not in API endpoints:

```python
# app/services/company.py
class CompanyService:
    def __init__(self, db: Session):
        self.db = db

    def create_company(self, data: CompanyCreate, owner_id: str, organization_id: str) -> Company:
        """Business logic for company creation."""
        # Validation
        # Database operations
        # Trigger workflows
        return company

# app/api/endpoints/companies.py
@router.post("/")
def create_company_endpoint(
    data: CompanyCreate,
    user: AuthenticatedUser = Depends(get_current_user(required_roles=["company.create"])),
    org_context: OrganizationContext = Depends(get_user_organization)
):
    """API endpoint delegates to service layer."""
    service = CompanyService(db=get_db())
    company = service.create_company(data, user.sub, org_context.organization_id)
    return company
```

### Repository Pattern (Optional)

For complex queries, consider repository classes:

```python
class CompanyRepository:
    def __init__(self, db: Session):
        self.db = db

    def find_by_id(self, company_id: int) -> Company | None:
        return self.db.query(Company).filter(Company.id == company_id).first()

    def find_by_organization(self, organization_id: str, skip: int = 0, limit: int = 100) -> list[Company]:
        return self.db.query(Company).filter(
            Company.organization_id == organization_id,
            Company.is_deleted == False
        ).offset(skip).limit(limit).all()
```

---

## 🔒 Security Best Practices

### Input Validation

**ALWAYS use Pydantic models** for validation:

```python
from pydantic import BaseModel, field_validator, HttpUrl

class CompanyCreate(BaseModel):
    name: str
    website: HttpUrl

    @field_validator('name')
    @classmethod
    def validate_name(cls, v: str) -> str:
        if not v or len(v.strip()) == 0:
            raise ValueError('Company name cannot be empty')
        if len(v) > 255:
            raise ValueError('Company name too long')
        return v.strip()
```

**NEVER**:
- Manually sanitize input (rely on parameterized queries)
- Use string concatenation for SQL queries
- Trust user input without validation

### Authentication & Authorization

**All authentication is handled via Internal JWT verification.** The global-service
gateway validates Keycloak JWTs and forwards requests with `Authorization: Internal {token}`.
Screen only verifies these Internal JWTs — no Keycloak SDK needed.

#### Basic Route Protection

Use `get_current_user()` dependency with `required_roles` parameter:

```python
from fastapi import Depends, APIRouter
from app.core.auth import AuthenticatedUser, get_current_user

router = APIRouter()

@router.get("/admin/companies")
async def list_all_companies(
    user: AuthenticatedUser = Depends(get_current_user(required_roles=["admin"]))
):
    """Admin endpoint - requires 'admin' role.

    Requires admin role for access.
    """
    # Role checking happens automatically via dependency
    # If user lacks 'admin' role, returns 403 automatically
    return {"companies": [...]}
```

#### Permission Hierarchy

**Global Admin Roles**:
- `admin` - Full system access (admin endpoints)
- `admin.organizations` - Organization administration
- `admin.costs` - Cost analysis access

**User-Level Roles** (apply to user's own organization):
- `organization.read` - Read access to user's organization
- `organization.write` - Modify organization and manage team
- `company.view` - View companies in user's organization
- `company.create` - Create/search companies
- `company.update` - Update companies (future)
- `company.delete` - Delete companies

#### Common Patterns

**Single Role Required**:
```python
@router.post("/companies")
def create_company(
    data: CompanyCreate,
    user: AuthenticatedUser = Depends(get_current_user(required_roles=["company.create"])),
    org_context: OrganizationContext = Depends(get_user_organization)
):
    """Requires company.create role for access."""
    # User automatically has company.create role if we reach here
```

**Multiple Roles (OR logic)**:
```python
# User needs ANY ONE of these roles
user: AuthenticatedUser = Depends(get_current_user(
    required_roles=["admin", "admin.organizations"]
))
```

**Organization Context**:
For user organization operations, combine role check with organization context:

```python
from app.core.auth import AuthenticatedUser, get_current_user
from app.core.organization_context import get_user_organization, OrganizationContext

@router.get("/folders")
def list_folders(
    user: AuthenticatedUser = Depends(get_current_user(required_roles=["organization.read"])),
    org_context: OrganizationContext = Depends(get_user_organization),
    db: Session = Depends(get_db)
):
    """Requires organization.read role for access."""
    # org_context.organization_id contains user's organization UUID
    # org_context.username contains user's username
```

#### Advanced: Conditional Role Checks

For conditional logic WITHIN endpoints, use helper functions from `app/core/auth.py`:

```python
from app.core.auth import verify_role_access, verify_any_role_access

@router.get("/items")
def get_items(
    include_sensitive: bool = False,
    user: AuthenticatedUser = Depends(get_current_user(required_roles=["organization.read"]))
):
    """Conditionally require higher permissions based on query params."""
    items = get_base_items()

    if include_sensitive:
        # Additional role check for sensitive data
        verify_role_access(user, "admin")  # Raises 403 if not admin
        items = include_sensitive_fields(items)

    return items
```

**Helper Functions**:
- `verify_role_access(user, role)` - Check single role, raise 403 if missing
- `verify_any_role_access(user, roles)` - Check if user has ANY of the roles

#### Resource-Level Checks

For business logic validation (ownership, organization membership), use functions from `app/core/security.py`:

```python
from app.core.auth import AuthenticatedUser, get_current_user
from app.core.security import verify_company_organization_access

@router.get("/companies/{company_id}")
def get_company(
    company_id: int,
    user: AuthenticatedUser = Depends(get_current_user(required_roles=["company.view"])),
    org_context: OrganizationContext = Depends(get_user_organization),
    db: Session = Depends(get_db)
):
    """Requires company.view role for access."""
    company = db.query(Company).filter(Company.id == company_id).first()

    # Verify company belongs to user's organization
    verify_company_organization_access(company, org_context)

    return company
```

#### Docstring Convention

**ALWAYS document required roles in docstrings**:

```python
@router.post("/folders")
def create_folder(
    folder: FolderCreate,
    user: AuthenticatedUser = Depends(get_current_user(required_roles=["organization.write"])),
    org_context: OrganizationContext = Depends(get_user_organization)
):
    """Create a new folder in the organization.

    Requires organization.write role for access.
    """
```

---

## 🗄️ Database Guidelines

### User References

**CRITICAL**: User IDs are Keycloak UUIDs (from JWT `sub` claim), NOT usernames.

```python
# ✅ CORRECT: Use UUID from JWT sub claim
class Company(Base):
    owner_id = Column(String, nullable=False)  # Keycloak UUID
    owner_username = Column(String)            # Denormalized for display

# When creating:
def create_company(user: AuthenticatedUser):
    company = Company(
        owner_id=user.sub,              # UUID from JWT
        owner_username=user.preferred_username  # Username for display
    )
```

### Migrations

**ALWAYS create migrations from within Docker**:

```bash
# Create migration
docker compose -f docker-compose.dev.yml exec backend alembic revision -m "description"

# Apply migrations
docker compose -f docker-compose.dev.yml exec backend alembic upgrade head

# Check current version
docker compose -f docker-compose.dev.yml exec backend alembic current
```

**Migration Best Practices**:
- Test migrations on copy of production data
- Include both upgrade and downgrade logic
- Document data migrations separately from schema changes
- Use Alembic's default hash naming (not sequential numbers)

---

## 🧪 Testing

### Test Structure

```
tests/
├── unit/              # Fast, isolated tests
│   ├── test_services.py
│   └── test_models.py
├── integration/       # Database + external services
│   ├── test_api.py
│   └── test_workflows.py
└── conftest.py        # Pytest fixtures
```

### Writing Tests

```python
import pytest
from app.core.exceptions import AuthorizationError
from app.services.company import CompanyService

def test_create_company_success(db_session, mock_user):
    """Test successful company creation."""
    service = CompanyService(db_session)
    company = service.create_company(
        data=CompanyCreate(name="Test Co", website="test.com"),
        owner_id=mock_user.sub,
        organization_id="org-uuid-123"
    )
    assert company.name == "Test Co"
    assert company.organization_id == "org-uuid-123"

def test_create_company_unauthorized(db_session, mock_user_no_perms):
    """Test company creation fails without permission."""
    service = CompanyService(db_session)
    with pytest.raises(AuthorizationError):
        service.create_company(...)
```

**Test Coverage Target**: >80% for all new code, 100% for security-critical code.

---

## 🐳 Docker Development

### Running Backend

```bash
# Start services
docker compose -f docker-compose.dev.yml up -d

# View logs
docker compose -f docker-compose.dev.yml logs backend -f

# Restart backend
docker compose -f docker-compose.dev.yml restart backend

# Enter backend shell
docker compose -f docker-compose.dev.yml exec backend bash

# Run Python script
docker compose -f docker-compose.dev.yml exec backend python script_name.py
```

### Environment Variables

Required environment variables (set in `.env` or docker-compose):

```env
# Database
DATABASE_URL=postgresql://user:pass@db:5432/mint_db

# Keycloak
KEYCLOAK_SERVER_URL=https://keycloak.domain.com/auth
KEYCLOAK_REALM=mint-realm
KEYCLOAK_CLIENT_ID=mint-backend
KEYCLOAK_CLIENT_SECRET=secret
KEYCLOAK_ADMIN_SECRET=admin-secret
```

---

## 📚 Additional Resources

### Internal Documentation
- **Spec**: @.agent-os/specs/2025-11-06-backend-code-quality-refactor/spec.md
- **Python Standards**: @.agent-os/standards/python-coding-standards.md
- **Tasks**: @.agent-os/specs/2025-11-06-backend-code-quality-refactor/tasks.md

### External Documentation
- **FastAPI**: https://fastapi.tiangolo.com/
- **Pydantic**: https://docs.pydantic.dev/
- **SQLAlchemy**: https://docs.sqlalchemy.org/
- **Alembic**: https://alembic.sqlalchemy.org/
- **Ruff**: https://docs.astral.sh/ruff/
- **dify-client**: https://github.com/langgenius/dify/tree/main/sdks/python-client

---

## 🎯 Quick Reference

### Common Commands

```bash
# Linting
ruff check .
ruff format .

# Testing
pytest tests/
pytest tests/unit/ -v
pytest --cov=app tests/

# Migrations
docker compose -f docker-compose.dev.yml exec backend alembic upgrade head
docker compose -f docker-compose.dev.yml exec backend alembic revision -m "description"

# Logs
docker compose -f docker-compose.dev.yml logs backend -f
```

### Import Order

```python
# 1. Standard library
import logging
from typing import Any

# 2. Third-party
from fastapi import Depends, HTTPException
from sqlalchemy.orm import Session
from pydantic import BaseModel

# 3. Local application
from app.core.exceptions import AuthorizationError
from app.core.logging_config import get_logger
from app.models.company import Company
from app.services.company import CompanyService
```

### Code Review Checklist

Before committing:
- [ ] All functions have type hints
- [ ] Public functions have docstrings
- [ ] Using custom exceptions (not bare `Exception`)
- [ ] Using `logger` (not `print()`)
- [ ] Ruff linting passes
- [ ] Tests added/updated
- [ ] No sensitive data in logs
- [ ] Proper error handling
