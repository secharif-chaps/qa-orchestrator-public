"""Company management endpoints.

This module provides endpoints for:
- CRUD operations on companies
- Company search and filtering
- CSV import/validation
"""

import asyncio

from fastapi import APIRouter, Depends, HTTPException, Query, status
from fastapi_keycloak import OIDCUser
from pydantic import ValidationError
from sqlalchemy.orm import Session

from app.core.dependencies import get_company_service, get_global_service_client
from app.core.keycloak import idp
from app.core.logging_config import get_logger
from app.core.organization_context import OrganizationContext, get_user_organization
from app.core.security import (
    verify_company_modify_permission,
    verify_company_organization_access,
)
from app.database import get_db
from app.models.company import Company
from app.models.task import TaskStatus
from app.schemas.company import (
    CompanyCreate,
    CompanyCSVImportRequest,
    CompanyCSVImportResponse,
    CompanyCSVValidationError,
    CompanyCSVValidationRequest,
    CompanyCSVValidationResponse,
    CompanyResponse,
    CompanyUpdate,
)
from app.schemas.pagination import PaginatedResponse, PaginationParams, SortOrder
from app.services.company import CompanyService, _build_company_response
from app.services.global_service_client import (
    GlobalServiceClient,
    ModuleName,
    ReferenceType,
)
from app.services.token_manager import TOKENS_PER_COMPANY

logger = get_logger(__name__)

router = APIRouter(prefix="/companies", tags=["companies"])


@router.get("/recent", response_model=list[CompanyResponse])
async def get_recent_companies(
    limit: int = Query(5, ge=1, le=100, description="Number of recent companies to return"),
    service: CompanyService = Depends(get_company_service),
    global_service: GlobalServiceClient = Depends(get_global_service_client),
    org_context: OrganizationContext = Depends(get_user_organization),
    db: Session = Depends(get_db),
):
    """Get recent companies with their folder information.

    Only returns companies that the user has access to via folder sharing.
    """
    logger.info(f"GET /companies/recent - User: {org_context.username}, Limit: {limit}")

    # Get accessible company IDs for this user via global-service
    accessible_company_ids = await global_service.get_accessible_company_ids(
        org_id=org_context.organization_id,
        user_id=org_context.user_id,
        username=org_context.username,
    )

    companies = service.get_recent_companies(
        organization_id=org_context.organization_id,
        limit=limit,
        accessible_company_ids=accessible_company_ids,
    )

    # Enrich companies with folder info from global-service (parallel calls)
    async def fetch_folder_info(company: CompanyResponse) -> tuple[int | None, dict[str, str] | None]:
        if company.id is None:
            return None, None
        folder_info = await global_service.get_company_folder_info(
            org_id=org_context.organization_id,
            company_id=company.id,
            user_id=org_context.user_id,
            username=org_context.username,
        )
        return company.id, folder_info

    folder_results = await asyncio.gather(*(fetch_folder_info(c) for c in companies))
    folder_map = {cid: info for cid, info in folder_results if cid is not None and info is not None}

    for company in companies:
        if company.id in folder_map:
            info = folder_map[company.id]
            company.folder_id = info.get("folder_id", None)
            company.folder_name = info.get("folder_name", None)
        else:
            logger.warning(
                f"Company {company.id} ({company.name}) has no folder info - every company should belong to a folder",
                extra={"company_id": company.id, "organization_id": org_context.organization_id},
            )

    return companies


@router.get("/", response_model=PaginatedResponse[CompanyResponse])
async def get_companies(
    page: int = Query(1, ge=1, description="Page number (starting from 1)"),
    per_page: int = Query(10, ge=1, le=100, description="Items per page (max 100)"),
    size: int = Query(None, ge=1, le=100, description="Items per page (alias for per_page)"),
    sort: str = Query(None, description="Field to sort by (name, created_at)"),
    order: SortOrder = Query(SortOrder.DESC, description="Sort order"),
    name: str = Query(None, description="Filter companies by name (partial match)"),
    archived: bool = Query(False, description="Include archived (soft-deleted) companies"),
    service: CompanyService = Depends(get_company_service),
    org_context: OrganizationContext = Depends(get_user_organization),
):
    """Get paginated companies for the current organization."""
    effective_per_page = size if size is not None else per_page

    filter_info = f"name='{name}'" if name else "no filters"
    logger.info(
        f"GET /companies - User: {org_context.username} - Page: {page}, Size: {effective_per_page}, {filter_info}"
    )

    pagination_params = PaginationParams(page=page, per_page=effective_per_page, sort=sort, order=order)
    return service.get_paginated_companies(
        pagination_params,
        organization_id=org_context.organization_id,
        name_filter=name,
        include_archived=archived,
    )


@router.get("/{company_id}", response_model=CompanyResponse)
async def get_company(
    company_id: int,
    language: str = Query(None, description="Language code for translations (fr, es, de, pt)"),
    archived: bool = Query(False, description="Include archived (soft-deleted) companies"),
    service: CompanyService = Depends(get_company_service),
    org_context: OrganizationContext = Depends(get_user_organization),
    db: Session = Depends(get_db),
):
    """Get a company by ID.

    Organization membership is enforced here. Folder-level access control
    is handled by global-service at the gateway level before proxying.

    Archived companies (soft-deleted) are accessible when archived=True is passed.
    Optionally specify a language code (fr, es, de, pt) to get translated content.
    """
    try:
        logger.info(
            f"GET /api/companies/{company_id} - User: {org_context.username}, "
            f"Organization: {org_context.organization_id}, Language: {language}, Archived: {archived}"
        )

        company = service.get_company(company_id, include_archived=archived)
        if not company:
            raise HTTPException(status_code=status.HTTP_404_NOT_FOUND, detail="Company not found")

        verify_company_organization_access(company, org_context)

        return _build_company_response(db, company, language=language)
    except HTTPException:
        raise
    except Exception as e:
        logger.error(f"Unexpected error in get_company: {str(e)}", exc_info=True)
        raise HTTPException(
            status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
            detail="An internal server error occurred while retrieving the company",
        )


@router.get("/by-name/{name}", response_model=CompanyResponse)
async def get_company_by_name(
    name: str,
    service: CompanyService = Depends(get_company_service),
    org_context: OrganizationContext = Depends(get_user_organization),
    db: Session = Depends(get_db),
):
    """Get a company by name (only if it belongs to user's organization)."""
    company = service.get_company_by_name(name.strip())
    verify_company_organization_access(company, org_context)
    return _build_company_response(db, company)


@router.post("/", response_model=CompanyResponse)
async def create_company(
    company_data: CompanyCreate,
    service: CompanyService = Depends(get_company_service),
    global_service: GlobalServiceClient = Depends(get_global_service_client),
    org_context: OrganizationContext = Depends(get_user_organization),
    db: Session = Depends(get_db),
):
    """Create a new company.

    Consumes tokens from the organization's global token balance via global-service.
    Each company creation costs 35 tokens.
    """
    logger.info(f"POST /api/companies/ - START - User: {org_context.username}, Data: {company_data.name[:50]}...")

    logger.info(
        f"Checking and consuming {TOKENS_PER_COMPANY} tokens for screen module "
        f"in organization: {org_context.organization_id}"
    )

    await global_service.consume_tokens(
        org_id=org_context.organization_id,
        amount=TOKENS_PER_COMPANY,
        module_name=ModuleName.SCREEN,
        reference_type=ReferenceType.company,
        reference_id=None,
        user_id=org_context.user_id,
        username=org_context.username,
        description="Company creation",
    )
    logger.info("Token consumed successfully via global-service")

    try:
        company = service.create_company(
            name=company_data.name,
            website=str(company_data.website),
            owner_id=org_context.user_id,
            owner_username=org_context.username,
            organization_id=org_context.organization_id,
        )
        logger.info(f"Company created successfully - ID: {company.id}, Name: {company.name}")

        return _build_company_response(db, company)

    except ValidationError as e:
        logger.error(f"Validation error: {str(e)}")
        raise HTTPException(
            status_code=status.HTTP_400_BAD_REQUEST,
            detail=f"Validation error: {str(e)}",
        )
    except ValueError as e:
        logger.error(f"Value error: {str(e)}")
        raise HTTPException(
            status_code=status.HTTP_400_BAD_REQUEST,
            detail=f"Invalid input: {str(e)}",
        )
    except Exception as e:
        logger.error(f"Unexpected error creating company: {str(e)}", exc_info=True)
        raise HTTPException(
            status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
            detail="An internal server error occurred while creating the company",
        )


@router.put("/{company_id}", response_model=CompanyResponse)
async def update_company(
    company_id: int,
    company_data: CompanyUpdate,
    service: CompanyService = Depends(get_company_service),
    org_context: OrganizationContext = Depends(get_user_organization),
    db: Session = Depends(get_db),
):
    """Update a company (requires organization.write permission)."""
    verify_company_modify_permission(org_context, "organization.write")

    company = service.get_company(company_id)
    verify_company_organization_access(company, org_context)

    if company_data.name:
        company.name = company_data.name
    if company_data.website:
        company.website = company_data.website

    updated_company = service.update_company(company)
    return _build_company_response(db, updated_company)


@router.delete("/{company_id}", response_model=CompanyResponse)
async def soft_delete_company(
    company_id: int,
    service: CompanyService = Depends(get_company_service),
    org_context: OrganizationContext = Depends(get_user_organization),
    db: Session = Depends(get_db),
):
    """Soft delete a company (archive) - requires organization.write permission."""
    verify_company_modify_permission(org_context, "organization.write")

    company = service.get_company(company_id)
    if not company:
        raise HTTPException(status_code=status.HTTP_404_NOT_FOUND, detail="Company not found")

    verify_company_organization_access(company, org_context)

    deleted_company = service.soft_delete_company(company_id)
    if not deleted_company:
        raise HTTPException(
            status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
            detail="Failed to archive company",
        )

    return _build_company_response(db, deleted_company)


@router.post("/{company_id}/restore", response_model=CompanyResponse)
async def restore_company(
    company_id: int,
    service: CompanyService = Depends(get_company_service),
    org_context: OrganizationContext = Depends(get_user_organization),
    db: Session = Depends(get_db),
):
    """Restore a soft-deleted company - requires organization.write permission."""
    logger.info(f"POST /api/companies/{company_id}/restore - User: {org_context.username}")

    verify_company_modify_permission(org_context, "organization.write")

    restored_company = service.restore_company(company_id)
    if not restored_company:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail="Company not found or not deleted",
        )

    verify_company_organization_access(restored_company, org_context)
    return _build_company_response(db, restored_company)


@router.post("/{company_id}/refresh", response_model=CompanyResponse)
async def refresh_company(
    company_id: int,
    service: CompanyService = Depends(get_company_service),
    global_service: GlobalServiceClient = Depends(get_global_service_client),
    org_context: OrganizationContext = Depends(get_user_organization),
    user: OIDCUser = Depends(idp.get_current_user(required_roles=["company.create"])),
    db: Session = Depends(get_db),
):
    """Refresh company data by re-running all tasks."""
    logger.info(
        "Refreshing company data",
        extra={
            "company_id": company_id,
            "user_id": org_context.user_id,
            "username": org_context.username,
            "organization_id": org_context.organization_id,
        },
    )

    try:
        company = _get_and_verify_company(company_id, org_context, service)
        _verify_ownership(company, org_context)
        _verify_all_tasks_succeeded(company_id, service)

        await global_service.consume_tokens(
            org_id=org_context.organization_id,
            amount=TOKENS_PER_COMPANY,
            module_name=ModuleName.SCREEN,
            reference_type=ReferenceType.refresh,
            reference_id=str(company_id),
            user_id=org_context.user_id,
            username=org_context.username,
            description="Company refresh",
        )

        try:
            refreshed_company = service.refresh_company(company_id)
        except Exception as e:
            logger.error(
                "Refresh failed after token consumption - tokens not refunded",
                extra={"company_id": company_id, "error": str(e)},
            )
            raise

        return _build_company_response(db, refreshed_company)

    except HTTPException:
        raise
    except Exception as e:
        logger.error(
            "Unexpected error during company refresh",
            exc_info=True,
            extra={
                "company_id": company_id,
                "user_id": org_context.user_id,
                "error": str(e),
            },
        )
        raise HTTPException(
            status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
            detail="An internal server error occurred while refreshing the company",
        )


def _get_and_verify_company(company_id: int, org_context: OrganizationContext, service: CompanyService) -> Company:
    """Get company and verify organization access."""
    company = service.get_company(company_id)
    if not company:
        raise HTTPException(status_code=status.HTTP_404_NOT_FOUND, detail="Company not found")
    verify_company_organization_access(company, org_context)
    return company


def _verify_ownership(company: Company, org_context: OrganizationContext) -> None:
    """Verify that the current user owns the company."""
    if company.owner_id != org_context.user_id:
        raise HTTPException(
            status_code=status.HTTP_403_FORBIDDEN, detail="Only the company owner can refresh company data"
        )


def _verify_all_tasks_succeeded(company_id: int, service: CompanyService) -> None:
    """Verify all company tasks have succeeded before allowing refresh."""
    tasks = service.get_company_tasks(company_id)

    if not all(task.status == TaskStatus.SUCCEEDED for task in tasks):
        raise HTTPException(
            status_code=status.HTTP_400_BAD_REQUEST, detail="All tasks must be completed successfully before refreshing"
        )


@router.get("/archived/list", response_model=list[CompanyResponse])
async def get_archived_companies(
    service: CompanyService = Depends(get_company_service),
    org_context: OrganizationContext = Depends(get_user_organization),
    db: Session = Depends(get_db),
):
    """Get all archived (soft-deleted) companies in the organization."""
    companies = service.get_archived_companies(organization_id=org_context.organization_id)
    return [_build_company_response(db, company) for company in companies]


@router.post("/csv/validate", response_model=CompanyCSVValidationResponse)
async def validate_csv_companies(
    validation_request: CompanyCSVValidationRequest,
    service: CompanyService = Depends(get_company_service),
    global_service: GlobalServiceClient = Depends(get_global_service_client),
    org_context: OrganizationContext = Depends(get_user_organization),
):
    """Validate CSV company data without creating companies."""
    logger.info(f"CSV validation request - User: {org_context.username}, Rows: {len(validation_request.companies)}")

    verify_company_modify_permission(org_context, "company.create")

    validation_result = service.validate_csv_companies(
        companies=validation_request.companies,
        organization_id=org_context.organization_id,
        token_manager=None,
    )

    try:
        available_tokens = await global_service.get_balance(
            org_id=org_context.organization_id,
            user_id=org_context.user_id,
            username=org_context.username,
        )
        has_sufficient_tokens = available_tokens >= validation_result.tokens_required
    except HTTPException:
        logger.warning(
            "Global-service unavailable during CSV validation, skipping token check",
            extra={"organization_id": org_context.organization_id},
        )
        available_tokens = None
        has_sufficient_tokens = True

    errors = list(validation_result.errors)
    if not has_sufficient_tokens and validation_result.valid_count > 0:
        errors.append(
            CompanyCSVValidationError(
                row_number=0,
                field="tokens",
                error=f"Insufficient tokens. Required: {validation_result.tokens_required}, Available: {available_tokens}",
            )
        )

    return CompanyCSVValidationResponse(
        valid_count=validation_result.valid_count,
        error_count=len(errors),
        errors=errors,
        has_sufficient_tokens=has_sufficient_tokens,
        tokens_required=validation_result.tokens_required,
        tokens_available=available_tokens,
    )


@router.post("/csv/import", response_model=CompanyCSVImportResponse)
async def import_csv_companies(
    import_request: CompanyCSVImportRequest,
    service: CompanyService = Depends(get_company_service),
    global_service: GlobalServiceClient = Depends(get_global_service_client),
    org_context: OrganizationContext = Depends(get_user_organization),
):
    """Import companies from CSV data."""
    logger.info(f"CSV import request - User: {org_context.username}, Rows: {len(import_request.companies)}")

    verify_company_modify_permission(org_context, "company.create")

    validation = service.validate_csv_companies(
        companies=import_request.companies,
        organization_id=org_context.organization_id,
        token_manager=None,
    )

    tokens_needed = validation.valid_count * TOKENS_PER_COMPANY

    if tokens_needed > 0:
        logger.info(f"Consuming {tokens_needed} tokens for CSV import via global-service")
        await global_service.consume_tokens(
            org_id=org_context.organization_id,
            amount=tokens_needed,
            module_name=ModuleName.SCREEN,
            reference_type=ReferenceType.csv_import,
            reference_id=None,
            user_id=org_context.user_id,
            username=org_context.username,
            description=f"CSV import of {validation.valid_count} companies",
        )

    result = service.import_csv_companies(
        companies=import_request.companies,
        owner_id=org_context.user_id,
        owner_username=org_context.username,
        organization_id=org_context.organization_id,
        skip_invalid=import_request.skip_invalid,
    )

    return result
