# Python Backend Coding Standards

## Code Style & Formatting

### Linter & Formatter

**Use Ruff with default configuration** - no custom config needed.

```bash
# Check for issues
ruff check .

# Auto-fix issues
ruff check --fix .

# Format code
ruff format .
```

**Default settings**:
- Line length: 88 characters
- Quote style: double quotes
- Indent: 4 spaces
- Target version: Python 3.9+

### Import Organization

Organize imports in three groups separated by blank lines:

```python
# 1. Standard library
import logging
from datetime import datetime
from typing import Any

# 2. Third-party packages
from fastapi import Depends, HTTPException
from sqlalchemy.orm import Session
from pydantic import BaseModel, field_validator

# 3. Local application imports
from app.core.exceptions import AuthorizationError, ResourceNotFoundError
from app.core.logging_config import get_logger
from app.models.company import Company
from app.services.company import CompanyService
```

**Import rules**:
- Use absolute imports (not relative)
- Group imports by package
- Sort alphabetically within groups
- Ruff handles this automatically

---

## Type Hints

### Required Type Hints

**ALL** functions must have type hints for parameters and return values.

```python
# ✅ CORRECT
def get_company_by_id(company_id: int, organization_id: int) -> Company | None:
    """Retrieve company by ID within organization."""
    return db.query(Company).filter(Company.id == company_id).first()

# ❌ INCORRECT - Missing type hints
def get_company_by_id(company_id, organization_id):
    return db.query(Company).filter(Company.id == company_id).first()
```

### Type Hint Syntax

**Use Python 3.10+ union syntax** with `|` (not `typing.Union`):

```python
# ✅ CORRECT - Modern syntax
from typing import Any

def process_data(data: dict[str, Any] | None) -> list[str] | None:
    if data is None:
        return None
    return list(data.keys())

# ❌ INCORRECT - Old syntax
from typing import Union, Dict, List, Optional

def process_data(data: Optional[Dict[str, Any]]) -> Optional[List[str]]:
    pass
```

**Built-in generic types** (Python 3.9+):
- `list[T]` not `List[T]`
- `dict[K, V]` not `Dict[K, V]`
- `set[T]` not `Set[T]`
- `tuple[T, ...]` not `Tuple[T, ...]`

---

## Exception Handling

### Custom Exception Hierarchy

**ALWAYS** use custom exceptions from `app/core/exceptions.py`:

```python
from app.core.exceptions import (
    AuthenticationError,      # Authentication fails
    AuthorizationError,       # Permission denied
    ValidationError,          # Business logic validation
    ResourceNotFoundError,    # Resource doesn't exist
    DatabaseError,            # Database operation fails
    ExternalServiceError      # External service fails
)
```

### Exception Handling Pattern

```python
from app.core.exceptions import DatabaseError, ExternalServiceError
from app.core.logging_config import get_logger

logger = get_logger(__name__)

def process_company_workflow(company_id: int) -> dict[str, Any]:
    """Process workflow for company."""
    try:
        company = db.query(Company).filter(Company.id == company_id).first()
        if not company:
            raise ResourceNotFoundError(
                "Company not found",
                details={"company_id": company_id}
            )
        return result

    except sqlalchemy.exc.SQLAlchemyError as e:
        logger.error(
            "Database error during workflow",
            exc_info=e,
            extra={"company_id": company_id}
        )
        raise DatabaseError(
            "Failed to retrieve company",
            details={"company_id": company_id, "error": str(e)}
        ) from e
```

**Exception Handling Rules**:
- ❌ NEVER use bare `except Exception`
- ✅ Catch specific exception types
- ✅ Include context in exception details
- ✅ Use `raise ... from e` to preserve exception chain
- ✅ Log with `exc_info=True` to include stack trace

---

## Logging

### Logger Setup

**Use `get_logger(__name__)`** in every module:

```python
from app.core.logging_config import get_logger

logger = get_logger(__name__)
```

### Structured Logging

**ALWAYS include context** in `extra` dict:

```python
# ✅ CORRECT - Structured logging with context
logger.info(
    "Processing company",
    extra={
        "company_id": company_id,
        "organization_id": organization_id,
        "user": user.username
    }
)

# ❌ INCORRECT - Unstructured logging
logger.info(f"Processing company {company_id} in organization {organization_id}")

# ❌ INCORRECT - Using print()
print(f"Processing company {company_id}")
```

### Log Levels

| Level | Usage | Example |
|-------|-------|---------|
| `DEBUG` | Detailed diagnostic info | Loop iterations, variable values |
| `INFO` | General informational | Request started, task completed |
| `WARNING` | Unexpected but non-blocking | Retry attempt, deprecated usage |
| `ERROR` | Error requiring attention | Failed operation, caught exception |
| `CRITICAL` | System failure condition | Database unreachable, service down |

### Logging Best Practices

- ✅ Include relevant IDs in `extra`
- ✅ Use `exc_info=True` when logging exceptions
- ❌ NEVER log sensitive data (passwords, tokens, PII, API keys)
- ✅ Keep log messages concise and actionable

---

## Documentation

### Docstrings

**Use Google-style docstrings** for all public functions:

```python
def create_company(
    data: CompanyCreate,
    owner_id: str,
    organization_id: int
) -> Company:
    """Create a new company in the specified organization.

    Args:
        data: Company creation data validated by Pydantic
        owner_id: Keycloak UUID of the creating user
        organization_id: Target organization ID

    Returns:
        Newly created Company instance

    Raises:
        ValidationError: If company name or website is invalid
        AuthorizationError: If user lacks company.create permission
        DatabaseError: If database operation fails
    """
    # Implementation here
```

### Comments

**Comments explain WHY, not WHAT**:

```python
# ✅ CORRECT - Explains reasoning
# Use JWT-only permission check to avoid race condition between
# token revocation and DB permission update
if not user.has_permission("company.create"):
    raise AuthorizationError("Missing permission")

# ❌ INCORRECT - States the obvious
# Check if user has permission
if not user.has_permission("company.create"):
    raise AuthorizationError("Missing permission")
```

---

## Function Design

### Function Length

**Target**: Functions should be under 50 lines.

If a function exceeds 50 lines, extract helper functions:

```python
# ✅ CORRECT - Extracted helper functions
def process_company_workflow(company_id: int) -> dict:
    """Process complete workflow for company."""
    _validate_company(company_id)
    data = _fetch_company_data(company_id)
    analysis = _run_ai_analysis(data)
    _store_results(company_id, analysis)
    return analysis

def _validate_company(company_id: int) -> None:
    """Validate company exists and user has permission."""
    # Validation logic

def _fetch_company_data(company_id: int) -> dict:
    """Fetch all required data for workflow."""
    # Data fetching logic
```

### Cyclomatic Complexity

**Target**: Complexity ≤ 10 per function.

Use early returns to reduce nesting:

```python
# ✅ CORRECT - Low complexity (early returns)
def get_company_data(company_id: int, user: User) -> dict | None:
    """Get company data with permission checks."""
    company = db.query(Company).filter(Company.id == company_id).first()

    if not company:
        return None

    if company.is_deleted:
        return None

    if company.organization_id != user.organization_id:
        raise AuthorizationError("Company not in organization")

    if not user.has_permission("company.view"):
        raise AuthorizationError("Permission denied")

    return {"id": company.id, "name": company.name}
```

---

## Pydantic Schemas

### BaseModel Patterns

```python
from pydantic import BaseModel, Field, ConfigDict, field_validator
from typing import Optional
from datetime import datetime

# Base schema for shared fields
class CompanyBase(BaseModel):
    name: str = Field(..., min_length=2, max_length=100)
    website: str = Field(..., min_length=4, max_length=255)

# Create schema (strict validation)
class CompanyCreate(CompanyBase):
    pass

# Update schema (all fields optional)
class CompanyUpdate(BaseModel):
    name: str | None = Field(None, min_length=2, max_length=100)
    website: str | None = Field(None, min_length=4, max_length=255)

# Response schema (includes all fields)
class CompanyResponse(CompanyBase):
    id: int
    owner_username: str
    created_at: datetime
    updated_at: datetime

    model_config = ConfigDict(from_attributes=True)
```

### Field Validators

```python
from pydantic import field_validator

class CompanyCreate(BaseModel):
    name: str
    website: str

    @field_validator('name')
    @classmethod
    def validate_name(cls, v: str) -> str:
        """Validate and sanitize company name"""
        return InputValidator.validate_company_name(v)

    @field_validator('website')
    @classmethod
    def validate_website(cls, v: str) -> str:
        """Validate and sanitize website URL"""
        return InputValidator.validate_website_url(v)
```

---

## SQLAlchemy Models

### Model Definition

```python
from datetime import datetime
from sqlalchemy import Column, String, Integer, DateTime, JSON, ForeignKey, Boolean
from sqlalchemy.orm import relationship
from app.database import Base

class Company(Base):
    __tablename__ = "companies"

    # Primary key
    id = Column(Integer, primary_key=True, index=True)

    # String fields
    name = Column(String, index=True, nullable=False)
    website = Column(String, index=True, nullable=False)
    owner_username = Column(String, index=True, nullable=False)

    # Foreign keys
    organization_id = Column(Integer, ForeignKey("organizations.id"), nullable=False)

    # Timestamps
    created_at = Column(DateTime, default=datetime.utcnow)
    updated_at = Column(DateTime, default=datetime.utcnow, onupdate=datetime.utcnow)

    # Boolean flags
    is_deleted = Column(Boolean, default=False, nullable=False)

    # JSON fields
    profile = Column(JSON, default=dict)

    # Relationships
    organization = relationship("Organization", back_populates="companies")
    tasks = relationship("Task", back_populates="company", cascade="all, delete-orphan")
```

### Important Model Patterns

```python
# ✅ Use Column with proper constraints
name = Column(String, index=True, nullable=False)  # Required, indexed
description = Column(String, nullable=True)  # Optional

# ✅ Set proper defaults
is_active = Column(Boolean, default=True, nullable=False)
created_at = Column(DateTime, default=datetime.utcnow)
data = Column(JSON, default=dict)

# ✅ Index frequently queried columns
organization_id = Column(Integer, ForeignKey("organizations.id"), index=True)

# ❌ NEVER create users table or UUID foreign keys to users
# User data is stored in Keycloak, not in the database
```

---

## FastAPI Endpoints

### Endpoint Patterns

```python
from fastapi import APIRouter, Depends, HTTPException, status, Query

router = APIRouter(prefix="/companies", tags=["companies"])

# GET with query parameters
@router.get("/", response_model=PaginatedResponse[CompanyResponse])
async def get_companies(
    page: int = Query(1, ge=1),
    per_page: int = Query(10, ge=1, le=100),
    name: str = Query(None),
    service: CompanyService = Depends(get_company_service),
    organization_context: OrganizationContext = Depends(get_user_organization)
):
    """Get paginated companies for the current organization"""
    return service.get_paginated_companies(page, per_page, name_filter=name)

# POST create
@router.post("/", response_model=CompanyResponse, status_code=status.HTTP_201_CREATED)
async def create_company(
    company_data: CompanyCreate,
    service: CompanyService = Depends(get_company_service),
    organization_context: OrganizationContext = Depends(get_user_organization)
):
    """Create a new company"""
    verify_company_permission(organization_context, "company.create")
    return service.create_company(company_data)

# DELETE
@router.delete("/{company_id}", status_code=status.HTTP_204_NO_CONTENT)
async def delete_company(
    company_id: int,
    service: CompanyService = Depends(get_company_service),
    organization_context: OrganizationContext = Depends(get_user_organization)
):
    """Delete a company (soft delete)"""
    verify_company_permission(organization_context, "company.delete")
    service.delete_company(company_id)
```

### HTTP Status Codes

| Code | Usage |
|------|-------|
| 200 | Success |
| 201 | Created |
| 204 | No Content (delete) |
| 400 | Bad Request (validation) |
| 403 | Forbidden (permissions) |
| 404 | Not Found |
| 422 | Unprocessable Entity |
| 500 | Internal Server Error |

---

## Security

### Input Validation

**ALWAYS use Pydantic models** for validation:

```python
from pydantic import BaseModel, field_validator

class CompanyCreate(BaseModel):
    name: str
    website: str

    @field_validator('name')
    @classmethod
    def validate_name(cls, v: str) -> str:
        if not v or len(v.strip()) < 2:
            raise ValueError('Company name must be at least 2 characters')
        # Remove dangerous characters
        sanitized = re.sub(r'[<>&"\']', '', v.strip())
        return sanitized
```

### Security Rules

- ❌ NEVER manually sanitize input without Pydantic
- ✅ ALWAYS use parameterized queries (SQLAlchemy ORM)
- ❌ NEVER use string concatenation for SQL
- ❌ NEVER log sensitive data (passwords, tokens, API keys, PII)
- ✅ ALWAYS check permissions before operations

### Permission Checks

```python
def verify_company_permission(
    organization_context: OrganizationContext,
    permission: str
) -> None:
    """Verify user has required permission"""
    if not organization_context.has_permission(permission):
        raise HTTPException(
            status_code=status.HTTP_403_FORBIDDEN,
            detail=f"Missing required permission: {permission}"
        )
```

---

## Code Quality Workflow

### Before Every Commit

```bash
# 1. Check for issues
ruff check .

# 2. Auto-fix what's possible
ruff check --fix --unsafe-fixes .

# 3. Format code
ruff format .

# 4. Commit
git add -A && git commit -m "Your commit message"
```

### Code Review Checklist

- [ ] All functions have type hints
- [ ] Public functions have Google-style docstrings
- [ ] Using custom exceptions (not bare `Exception`)
- [ ] Using `logger` (not `print()`)
- [ ] Ruff linting passes
- [ ] Code formatted
- [ ] No sensitive data in logs
- [ ] Functions under 50 lines
- [ ] Permissions checked before operations
