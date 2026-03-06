# Screen Domain Models Reference

## Company (Primary Entity)

```python
class Company(Base):
    __tablename__ = "companies"

    id: Mapped[int] = mapped_column(primary_key=True)
    name: Mapped[str] = mapped_column(String(255))
    website: Mapped[str | None] = mapped_column(String(500))
    organization_id: Mapped[str] = mapped_column(String(36))
    owner_id: Mapped[str] = mapped_column(String(36))
    owner_username: Mapped[str] = mapped_column(String(255))
    is_deleted: Mapped[bool] = mapped_column(Boolean, default=False)
    created_at: Mapped[datetime] = mapped_column(DateTime(timezone=True))
    updated_at: Mapped[datetime] = mapped_column(DateTime(timezone=True))

    # Raw knowledge from data collection
    raw_mistral_knowledge: Mapped[str | None]
    raw_gpt_knowledge: Mapped[str | None]
    raw_wikipedia_knowledge: Mapped[str | None]
    raw_scraped_knowledge: Mapped[str | None]
    raw_pappers_knowledge: Mapped[str | None]

    # 1:1 section relationships
    profile_data: Mapped["CompanyProfile"] = relationship(uselist=False)
    digital_data: Mapped["CompanyDigital"] = relationship(uselist=False)
    timeline_data: Mapped["CompanyTimeline"] = relationship(uselist=False)
    products_data: Mapped["CompanyProducts"] = relationship(uselist=False)
    jobs_data: Mapped["CompanyJobs"] = relationship(uselist=False)
    csr_data: Mapped["CompanyCsr"] = relationship(uselist=False)
    press_data: Mapped["CompanyPress"] = relationship(uselist=False)

    # 1:N relationships
    tasks: Mapped[list["Task"]] = relationship(back_populates="company")
    online_services: Mapped[list["CompanyOnlineService"]] = relationship()
    social_media_accounts: Mapped[list["CompanySocialMediaAccount"]] = relationship()
    team_members: Mapped[list["CompanyTeamMember"]] = relationship()
```

## Task

```python
class Task(Base):
    __tablename__ = "tasks"

    id: Mapped[int] = mapped_column(primary_key=True)
    company_id: Mapped[int] = mapped_column(ForeignKey("companies.id"))
    task_type: Mapped[str] = mapped_column(String(50))
    status: Mapped[str] = mapped_column(String(20))  # pending, running, succeeded, error, blocked
    error_message: Mapped[str | None]
    token_cost: Mapped[int | None]
    started_at: Mapped[datetime | None]
    completed_at: Mapped[datetime | None]
    created_at: Mapped[datetime]

    company: Mapped["Company"] = relationship(back_populates="tasks")
    dependencies: Mapped[list["TaskDependency"]] = relationship()
```

## Folder & Organization

```python
class Folder(Base):
    __tablename__ = "folders"
    id: Mapped[int] = mapped_column(primary_key=True)
    name: Mapped[str] = mapped_column(String(255))
    organization_id: Mapped[str] = mapped_column(String(36))
    owner_id: Mapped[str] = mapped_column(String(36))
    owner_username: Mapped[str]

class Organization(Base):
    __tablename__ = "organizations"
    id: Mapped[str] = mapped_column(String(36), primary_key=True)  # Keycloak UUID
    token_balance: Mapped[int] = mapped_column(default=0)
    # Settings, not identity (identity is in Keycloak)
```

## Company Section Tables (1:1)

Each section is a separate table with FK to company:

- `CompanyProfile` - General info, description, industry
- `CompanyDigital` - Web presence, SEO, social media summary
- `CompanyTimeline` - Key dates, milestones
- `CompanyProducts` - Products and services
- `CompanyJobs` - Job market data
- `CompanyCsr` - Corporate social responsibility
- `CompanyPress` - Press coverage

## Company Child Tables (1:N)

- `CompanyOnlineService` - Individual web services
- `CompanySocialMediaAccount` - Social media profiles
- `CompanyTimelineEvent` - Individual events
- `CompanyProductItem` - Individual products
- `CompanyProductCategory` - Product categories
- `CompanyJobOffer` - Individual job listings
- `CompanyCsrInitiative` - CSR initiatives
- `CompanyPressItem` - Press articles
- `CompanyTeamMember` - Key people

## Pagination Pattern

```python
class PaginationParams(BaseModel):
    page: int = Field(default=1, ge=1)
    size: int = Field(default=10, ge=1, le=100)
    sort: str | None = None
    order: str = "desc"  # asc or desc

class PaginatedResponse(BaseModel, Generic[T]):
    items: list[T]
    total: int
    page: int
    size: int
    pages: int
```

## Service Injection Pattern

```python
# Services are instantiated with DB session, not injected via DI container
@router.get("/companies/{company_id}")
async def get_company(
    company_id: int,
    db: Session = Depends(get_db),
    org_context: OrganizationContext = Depends(get_user_organization),
):
    service = CompanyService(db=db)
    return service.get_company(company_id, org_context.organization_id)
```

## Logging Pattern

```python
from app.core.logging_config import get_logger

logger = get_logger(__name__)

logger.info("Operation completed", extra={
    "company_id": company.id,
    "org_id": org_context.organization_id,
    "task_type": task_type,
})
```
