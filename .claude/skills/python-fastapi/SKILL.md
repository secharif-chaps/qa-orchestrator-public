---
name: python-fastapi
description: Python backend development with FastAPI, SQLAlchemy, and Pydantic. Use when writing API endpoints, database models, migrations, or backend business logic. Follows Ruff formatting and strict type hints.
allowed-tools: Read, Write, Edit, Glob, Grep, Bash
---

# Python FastAPI Backend

## Code Style

- **Formatter**: Ruff (line length 120)
- **Type hints**: Required on all functions
- **Quotes**: Double quotes for strings
- **Imports**: Sorted with isort

## Type Hints

```python
from typing import Optional

def get_company(
    company_id: int,
    include_tasks: bool = False,
) -> Company:
    ...

async def list_companies(
    page: int = 1,
    size: int = 10,
) -> list[Company]:
    ...
```

## Pydantic Models

```python
from pydantic import BaseModel, Field

class CompanyCreate(BaseModel):
    name: str = Field(..., min_length=1, max_length=255)
    website: str | None = None

class CompanyResponse(BaseModel):
    id: int
    name: str
    website: str | None
    created_at: datetime

    model_config = ConfigDict(from_attributes=True)
```

## FastAPI Endpoints

```python
from fastapi import APIRouter, Depends, HTTPException, status

router = APIRouter(prefix="/companies", tags=["companies"])

@router.get("/{company_id}", response_model=CompanyResponse)
async def get_company(
    company_id: int,
    db: AsyncSession = Depends(get_db),
    user: OIDCUser = Depends(get_current_user),
) -> CompanyResponse:
    company = await company_service.get_by_id(db, company_id)
    if not company:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail=f"Company {company_id} not found",
        )
    return company
```

## SQLAlchemy Models

```python
from sqlalchemy import String, ForeignKey
from sqlalchemy.orm import Mapped, mapped_column, relationship

class Company(Base):
    __tablename__ = "companies"

    id: Mapped[int] = mapped_column(primary_key=True)
    name: Mapped[str] = mapped_column(String(255))
    organization_id: Mapped[str] = mapped_column(String(36))

    tasks: Mapped[list["Task"]] = relationship(back_populates="company")
```

## Error Handling

```python
# Custom exceptions
class CompanyNotFoundError(Exception):
    def __init__(self, company_id: int):
        self.company_id = company_id
        super().__init__(f"Company {company_id} not found")

# In endpoint
try:
    company = await service.get(company_id)
except CompanyNotFoundError:
    raise HTTPException(status_code=404, detail="Company not found")
```

For detailed patterns, see [coding-style.md](coding-style.md)
