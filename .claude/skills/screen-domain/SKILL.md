---
name: screen-domain
description: >
  ChapsMind screen backend domain architecture with FastAPI, SQLAlchemy, and Pydantic.
  Use when creating models, schemas, services, API endpoints, or Celery workers in the
  screen backend. Activates when working on apps/screen/app/ files including models/,
  schemas/, services/, api/endpoints/, workers/, or core/.
  CRITICAL - Always inject database session via Depends(get_db), use Pydantic for validation,
  and filter queries by organization_id for multi-tenancy.
allowed-tools: Read, Write, Edit, Glob, Grep, Bash
metadata:
  author: chaps-e
  version: "1.0"
---

## When to use this skill

- When creating or modifying SQLAlchemy models in `apps/screen/app/models/`
- When defining Pydantic schemas in `apps/screen/app/schemas/`
- When writing business logic in `apps/screen/app/services/`
- When creating API endpoints in `apps/screen/app/api/endpoints/`
- When working on Celery workers in `apps/screen/app/workers/`
- When configuring core modules in `apps/screen/app/core/`

# Screen Backend Domain

**CRITICAL**: Always use `Depends(get_db)` for session injection. Always filter by `organization_id` for tenant isolation. No user/organization tables in DB (Keycloak only).

## Architecture

```
apps/screen/
├── app/
│   ├── api/endpoints/     # FastAPI routers (20+ modules)
│   ├── models/            # SQLAlchemy ORM models
│   ├── schemas/           # Pydantic validation schemas
│   ├── services/          # Business logic layer
│   ├── repositories/      # Data access layer
│   ├── workers/           # Celery async tasks
│   ├── infrastructure/    # External integrations (Dify)
│   ├── core/              # Config, auth, middleware
│   └── main.py            # FastAPI app entry
├── alembic/               # Database migrations
└── tests/
```

## Model Pattern

```python
# apps/screen/app/models/company.py
from sqlalchemy import String, Boolean, DateTime, ForeignKey
from sqlalchemy.orm import Mapped, mapped_column, relationship

class Company(Base):
    __tablename__ = "companies"

    id: Mapped[int] = mapped_column(primary_key=True)
    name: Mapped[str] = mapped_column(String(255))
    website: Mapped[str | None] = mapped_column(String(500))
    organization_id: Mapped[str] = mapped_column(String(36))  # Keycloak UUID
    owner_id: Mapped[str] = mapped_column(String(36))          # Keycloak UUID
    owner_username: Mapped[str] = mapped_column(String(255))   # Denormalized
    is_deleted: Mapped[bool] = mapped_column(Boolean, default=False)
    created_at: Mapped[datetime] = mapped_column(DateTime(timezone=True), default=func.now())
    updated_at: Mapped[datetime] = mapped_column(DateTime(timezone=True), onupdate=func.now())

    # Relationships
    tasks: Mapped[list["Task"]] = relationship(back_populates="company")
    profile_data: Mapped["CompanyProfile"] = relationship(uselist=False)
```

### Key Model Rules

- **No user/org FK** - Use VARCHAR(36) for Keycloak UUIDs
- **Soft delete** - `is_deleted` boolean, never hard delete
- **Timestamps** - Always `created_at` + `updated_at` with timezone
- **Denormalize display fields** - `owner_username` avoids Keycloak lookups

## Schema Pattern

```python
# apps/screen/app/schemas/company.py
from pydantic import BaseModel, Field, ConfigDict

class CompanyCreate(BaseModel):
    name: str = Field(..., min_length=1, max_length=255)
    website: str | None = None

class CompanyResponse(BaseModel):
    id: int
    name: str
    website: str | None
    organization_id: str
    created_at: datetime

    model_config = ConfigDict(from_attributes=True)

class PaginatedResponse(BaseModel, Generic[T]):
    items: list[T]
    total: int
    page: int
    size: int
    pages: int
```

## Endpoint Pattern

```python
# apps/screen/app/api/endpoints/company.py
from fastapi import APIRouter, Depends, HTTPException, status
from app.core.keycloak import idp, OIDCUser
from app.core.organization import get_user_organization, OrganizationContext

router = APIRouter(prefix="/companies", tags=["companies"])

@router.get("/", response_model=PaginatedResponse[CompanyResponse])
async def list_companies(
    user: OIDCUser = Depends(idp.get_current_user(required_roles=["company.view"])),
    org_context: OrganizationContext = Depends(get_user_organization),
    db: Session = Depends(get_db),
    page: int = 1,
    size: int = 10,
    name: str | None = None,
) -> PaginatedResponse[CompanyResponse]:
    service = CompanyService(db=db)
    return service.list_companies(
        organization_id=org_context.organization_id,
        page=page,
        size=size,
        name_filter=name,
    )

@router.post("/", response_model=CompanyResponse, status_code=status.HTTP_201_CREATED)
async def create_company(
    data: CompanyCreate,
    user: OIDCUser = Depends(idp.get_current_user(required_roles=["company.create"])),
    org_context: OrganizationContext = Depends(get_user_organization),
    db: Session = Depends(get_db),
) -> CompanyResponse:
    service = CompanyService(db=db)
    return service.create_company(
        data=data,
        owner_id=org_context.user_id,
        owner_username=org_context.username,
        organization_id=org_context.organization_id,
    )
```

## Service Pattern

```python
# apps/screen/app/services/company.py
from app.core.logging_config import get_logger

logger = get_logger(__name__)

class CompanyService:
    def __init__(self, db: Session):
        self.db = db

    def get_company(self, company_id: int, organization_id: str) -> Company:
        company = self.db.query(Company).filter(
            Company.id == company_id,
            Company.organization_id == organization_id,
            Company.is_deleted == False,
        ).first()
        if not company:
            raise HTTPException(status_code=404, detail="Company not found")
        return company

    def create_company(
        self,
        data: CompanyCreate,
        owner_id: str,
        owner_username: str,
        organization_id: str,
    ) -> Company:
        company = Company(
            name=data.name,
            website=data.website,
            owner_id=owner_id,
            owner_username=owner_username,
            organization_id=organization_id,
        )
        self.db.add(company)
        self.db.commit()
        self.db.refresh(company)
        logger.info("Company created", extra={"company_id": company.id, "org_id": organization_id})
        return company
```

## Core Domain Entities

| Entity           | Table              | Purpose                             |
| ---------------- | ------------------ | ----------------------------------- |
| Company          | companies          | Primary entity, monitored companies |
| Task             | tasks              | AI workflow execution tracking      |
| TaskDependency   | task_dependencies  | Task prerequisite chains            |
| Folder           | folders            | Company organization                |
| FolderItem       | folder_items       | Company-folder association          |
| Organization     | organizations      | Org settings + token balance        |
| TokenTransaction | token_transactions | Token usage audit log               |
| Translation      | translations       | Multi-language company data         |
| CompanyProfile   | company_profiles   | 1:1 profile section                 |
| CompanyDigital   | company_digitals   | 1:1 digital presence                |
| CompanyTimeline  | company_timelines  | 1:1 timeline section                |

## Task Status Lifecycle

```
PENDING → RUNNING → SUCCEEDED
                  → ERROR
BLOCKED → (wait for dependencies) → PENDING
RUNNING (>5min timeout) → ERROR (lazy cleanup)
```

## Documentation

For detailed model definitions and service patterns, see:

- [Domain models reference](references/domain-models.md)
