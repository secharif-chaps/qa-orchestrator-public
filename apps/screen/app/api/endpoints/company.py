"""Company management endpoints.

This module provides endpoints for:
- CRUD operations on companies
- Company search and filtering
- CSV import/validation
- Company chat integration
"""


from fastapi import APIRouter, Depends, HTTPException, Query, status
from fastapi_keycloak import OIDCUser
from pydantic import ValidationError
from sqlalchemy.orm import Session

from app.core.dependencies import get_company_service, get_global_service_client
from app.core.keycloak import idp
from app.core.logging_config import get_logger
from app.core.organization import OrganizationContext, get_user_organization
from app.core.security import (
    verify_company_modify_permission,
    verify_company_organization_access,
)
from app.database import get_db
from app.models.company import Company
from app.models.organization import ModuleName, ReferenceType
from app.models.task import TaskStatus
from app.schemas.chat import ChatRequest, ChatResponse
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
from app.services.company_section_service import read_all_section_data
from app.services.dify import DifyService
from app.services.folder import FolderService
from app.services.global_service_client import (
    TOKENS_PER_COMPANY,
    GlobalServiceClient,
)

logger = get_logger(__name__)

router = APIRouter(prefix="/companies", tags=["companies"])


@router.get("/recent", response_model=list[CompanyResponse])
async def get_recent_companies(
    limit: int = Query(
        5, ge=1, le=100, description="Number of recent companies to return"
    ),
    service: CompanyService = Depends(get_company_service),
    org_context: OrganizationContext = Depends(get_user_organization),
    db: Session = Depends(get_db),
):
    """Get recent companies with their folder information.

    Only returns companies that the user has access to via folder sharing.
    """
    logger.info(
        f"GET /companies/recent - User: {org_context.username}, Limit: {limit}"
    )

    # Get accessible company IDs for this user
    accessible_company_ids = FolderService.get_accessible_company_ids(
        db,
        org_context.user_id,
        org_context.organization_id,
        username=org_context.username,
    )

    return service.get_recent_companies(
        organization_id=org_context.organization_id,
        limit=limit,
        accessible_company_ids=accessible_company_ids,
    )


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
        f"GET /companies - User: {org_context.username} - Page: {page}, "
        f"Size: {effective_per_page}, {filter_info}"
    )

    pagination_params = PaginationParams(
        page=page, per_page=effective_per_page, sort=sort, order=order
    )
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
    service: CompanyService = Depends(get_company_service),
    org_context: OrganizationContext = Depends(get_user_organization),
    user: OIDCUser = Depends(idp.get_current_user(required_roles=["organization.read"])),
    db: Session = Depends(get_db),
):
    """Get a company by ID (only if user has access via folder sharing).

    Access is granted if the company belongs to at least one folder that
    the user owns or has been shared with.
    Managers (organization.manage or admin.organizations) can access all companies.

    Optionally specify a language code (fr, es, de, pt) to get translated content.
    All translations are fetched from the normalized translations table.
    """
    try:
        logger.info(
            f"GET /api/companies/{company_id} - User: {org_context.username}, "
            f"Organization: {org_context.organization_id}, Language: {language}"
        )

        # Get the Company model for access checks
        company = service.get_company(company_id)
        if not company:
            logger.error(f"Company {company_id} not found")
            raise HTTPException(
                status_code=status.HTTP_404_NOT_FOUND, detail="Company not found"
            )

        # Verify organization access first
        logger.info(f"Company {company_id} found, verifying organization access")
        verify_company_organization_access(company, org_context)

        # Check folder-based access control
        # Managers (organization.manage or admin.organizations) can access all companies
        user_roles = user.realm_access.get('roles', [])
        if not FolderService.user_has_company_access(
            db,
            company_id,
            org_context.user_id,
            org_context.organization_id,
            username=org_context.username,
            user_roles=user_roles
        ):
            logger.warning(
                f"User {org_context.username} does not have folder access to company {company_id}",
                extra={"user_roles": user_roles}
            )
            raise HTTPException(
                status_code=status.HTTP_404_NOT_FOUND, detail="Company not found"
            )

        logger.info(f"User {org_context.username} has access to company {company_id}")

        # Build response with optional translation
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

    The screen module must be enabled for the organization.
    """
    logger.info(
        f"POST /api/companies/ - START - User: {org_context.username}, "
        f"Data: {company_data.name[:50]}..."
    )

    # Step 1: Consume tokens from global-service
    # Each company creation costs TOKENS_PER_COMPANY (35) tokens
    logger.info(
        f"Checking and consuming {TOKENS_PER_COMPANY} tokens for screen module "
        f"in organization: {org_context.organization_id}"
    )

    # Consume tokens via global-service API
    # InsufficientTokensError (402) and ModuleNotEnabledError (403) propagate directly
    await global_service.consume_tokens(
        org_id=org_context.organization_id,
        amount=TOKENS_PER_COMPANY,
        module_name=ModuleName.SCREEN,
        reference_type=ReferenceType.company,
        reference_id=None,  # Will be updated after company is created
        user_id=org_context.user_id,
        username=org_context.username,
        description="Company creation",
    )
    logger.info("Token consumed successfully via global-service")

    try:
        # Step 2: Input validation already handled by Pydantic CompanyCreate model
        logger.info(
            f"Processing company - Name: {company_data.name[:50]}, "
            f"Website: {str(company_data.website)[:50]}"
        )

        # Step 3: Create company using the authenticated user's ID and username
        logger.info(
            f"Calling service.create_company for authenticated user: "
            f"{org_context.username} ({org_context.user_id}) "
            f"in organization: {org_context.organization_id}"
        )
        company = service.create_company(
            name=company_data.name,
            website=str(company_data.website),
            owner_id=org_context.user_id,
            owner_username=org_context.username,
            organization_id=org_context.organization_id,
            callback_base_url=company_data.callback_base_url,
        )
        logger.info(f"Company created successfully - ID: {company.id}, Name: {company.name}")

        # Return CompanyResponse built from normalized tables
        return _build_company_response(db, company)

    except ValidationError as e:
        # Note: Per requirements, no token refunds on failure
        logger.error(f"Validation error: {str(e)}")
        raise HTTPException(
            status_code=status.HTTP_400_BAD_REQUEST,
            detail=f"Validation error: {str(e)}",
        )
    except ValueError as e:
        # Note: Per requirements, no token refunds on failure
        logger.error(f"Value error: {str(e)}")
        raise HTTPException(
            status_code=status.HTTP_400_BAD_REQUEST,
            detail=f"Invalid input: {str(e)}",
        )
    except Exception as e:
        # Note: Per requirements, no token refunds on failure
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
    """Update a company (requires organization.write permission).

    Note: Section data (profile, digital, etc.) cannot be updated via this endpoint.
    Section data is managed by the Dify workflow callbacks.
    """
    verify_company_modify_permission(org_context, "organization.write")

    company = service.get_company(company_id)
    verify_company_organization_access(company, org_context)

    # Only update core company fields
    if company_data.name:
        company.name = company_data.name
    if company_data.website:
        company.website = company_data.website

    # Note: Section data (profile, digital, timeline, etc.) is now managed by
    # normalized tables and updated via Dify callbacks, not through this endpoint.
    # The CompanyUpdate schema still has these fields for backward compatibility,
    # but they are ignored here.

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
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND, detail="Company not found"
        )

    verify_company_organization_access(company, org_context)

    deleted_company = service.soft_delete_company(company_id)
    if not deleted_company:
        raise HTTPException(
            status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
            detail="Failed to archive company",
        )

    return _build_company_response(db, deleted_company)


@router.post("/{company_id}/chatbot", response_model=ChatResponse)
async def chat_with_company(
    company_id: int,
    chat_request: ChatRequest,
    service: CompanyService = Depends(get_company_service),
    org_context: OrganizationContext = Depends(get_user_organization),
    db: Session = Depends(get_db),
):
    """Chat with AI about a company using Dify workflow."""
    logger.info(f"Chat request for company {company_id} by user {org_context.username}")

    try:
        company = service.get_company(company_id)
        verify_company_organization_access(company, org_context)

        dify_service = DifyService()

        # Read section data from normalized tables
        section_data = read_all_section_data(db, company.id)

        company_context = {
            "id": company.id,
            "name": company.name,
            "website": company.website,
            "profile": section_data.get("profile", {}),
            "digital": section_data.get("digital", {}),
            "timeline": section_data.get("timeline", {}),
            "products": section_data.get("products", {}),
            "jobs": section_data.get("jobs", {}),
            "csr": section_data.get("csr", {}),
            "press": section_data.get("press", {}),
            "team": section_data.get("team", []),
        }

        chat_history = []
        if chat_request.chat_history:
            chat_history = [
                {"role": msg.role, "content": msg.content}
                for msg in chat_request.chat_history
            ]

        response_data = await dify_service.send_chat_message(
            message=chat_request.message,
            company_context=company_context,
            chat_history=chat_history,
        )

        return ChatResponse(
            response=response_data.get("response", "No response received"),
            status=response_data.get("status", "success"),
        )

    except Exception as e:
        logger.error(f"Error in chat endpoint: {str(e)}")
        return ChatResponse(
            response="I'm sorry, I'm having trouble responding right now. Please try again in a moment.",
            status="error",
        )


@router.post("/{company_id}/restore", response_model=CompanyResponse)
async def restore_company(
    company_id: int,
    service: CompanyService = Depends(get_company_service),
    org_context: OrganizationContext = Depends(get_user_organization),
    db: Session = Depends(get_db),
):
    """Restore a soft-deleted company - requires organization.write permission."""
    logger.info(
        f"POST /api/companies/{company_id}/restore - User: {org_context.username}"
    )

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
    """Refresh company data by re-running all tasks.

    Args:
        company_id: ID of the company to refresh
        service: Company service instance
        global_service: Global service client for token consumption
        org_context: User's organization context
        user: Authenticated user from Keycloak
        db: Database session

    Returns:
        CompanyResponse with updated company data

    Raises:
        HTTPException 404: Company not found
        HTTPException 403: User is not the company owner
        HTTPException 400: Not all tasks have succeeded
        HTTPException 402: Insufficient tokens
        HTTPException 500: Unexpected server error
    """
    logger.info(
        "Refreshing company data",
        extra={
            "company_id": company_id,
            "user_id": org_context.user_id,
            "username": org_context.username,
            "organization_id": org_context.organization_id,
        }
    )

    try:
        company = _get_and_verify_company(company_id, org_context, service)
        _verify_ownership(company, org_context)
        _verify_all_tasks_succeeded(company_id, service)

        # Consume tokens via global-service
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

        logger.info(
            "Tokens consumed for company refresh via global-service",
            extra={
                "company_id": company_id,
                "tokens_consumed": TOKENS_PER_COMPANY,
                "organization_id": org_context.organization_id,
            }
        )

        try:
            refreshed_company = service.refresh_company(company_id)
        except Exception as e:
            # Note: Per spec, refunds require calling global-service add_tokens
            # For now we log the failure but don't refund (refund logic could be added)
            logger.error(
                "Refresh failed after token consumption - tokens not refunded",
                extra={"company_id": company_id, "error": str(e)},
            )
            raise

        logger.info(
            "Company refresh completed successfully",
            extra={
                "company_id": company_id,
                "company_name": refreshed_company.name,
            }
        )

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
            }
        )
        raise HTTPException(
            status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
            detail="An internal server error occurred while refreshing the company",
        )


def _get_and_verify_company(
    company_id: int, org_context: OrganizationContext, service: CompanyService
) -> Company:
    """Get company and verify organization access."""
    company = service.get_company(company_id)
    if not company:
        logger.error(
            "Company not found",
            extra={"company_id": company_id}
        )
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND, detail="Company not found"
        )
    verify_company_organization_access(company, org_context)
    return company


def _verify_ownership(company: Company, org_context: OrganizationContext) -> None:
    """Verify that the current user owns the company."""
    if company.owner_id != org_context.user_id:
        logger.warning(
            "User attempted to refresh company they don't own",
            extra={
                "company_id": company.id,
                "company_owner_id": company.owner_id,
                "requester_user_id": org_context.user_id,
            }
        )
        raise HTTPException(
            status_code=status.HTTP_403_FORBIDDEN,
            detail="Only the company owner can refresh company data"
        )


def _verify_all_tasks_succeeded(company_id: int, service: CompanyService) -> None:
    """Verify all company tasks have succeeded before allowing refresh."""
    tasks = service.get_company_tasks(company_id)

    if not all(task.status == TaskStatus.SUCCEEDED for task in tasks):
        failed_tasks = [
            {"id": t.id, "type": t.type, "status": t.status}
            for t in tasks if t.status != TaskStatus.SUCCEEDED
        ]
        logger.warning(
            "Cannot refresh company - not all tasks succeeded",
            extra={
                "company_id": company_id,
                "total_tasks": len(tasks),
                "failed_tasks": failed_tasks,
            }
        )
        raise HTTPException(
            status_code=status.HTTP_400_BAD_REQUEST,
            detail="All tasks must be completed successfully before refreshing"
        )


@router.get("/archived/list", response_model=list[CompanyResponse])
async def get_archived_companies(
    service: CompanyService = Depends(get_company_service),
    org_context: OrganizationContext = Depends(get_user_organization),
    db: Session = Depends(get_db),
):
    """Get all archived (soft-deleted) companies in the organization."""
    logger.info(
        f"GET /api/companies/archived/list - User: {org_context.username}"
    )

    companies = service.get_archived_companies(organization_id=org_context.organization_id)
    return [_build_company_response(db, company) for company in companies]


@router.post("/csv/validate", response_model=CompanyCSVValidationResponse)
async def validate_csv_companies(
    validation_request: CompanyCSVValidationRequest,
    service: CompanyService = Depends(get_company_service),
    global_service: GlobalServiceClient = Depends(get_global_service_client),
    org_context: OrganizationContext = Depends(get_user_organization),
):
    """Validate CSV company data without creating companies.

    Also checks if user has sufficient tokens for valid companies by querying
    the global-service for the actual token balance.
    """
    logger.info(
        f"CSV validation request - User: {org_context.username}, "
        f"Rows: {len(validation_request.companies)}"
    )

    verify_company_modify_permission(org_context, "company.create")

    # Validate CSV data (without token check)
    validation_result = service.validate_csv_companies(
        companies=validation_request.companies,
        organization_id=org_context.organization_id,
        token_manager=None,
    )

    # Get actual token balance from global-service
    available_tokens = await global_service.get_balance(
        org_id=org_context.organization_id,
        user_id=org_context.user_id,
        username=org_context.username,
    )

    # Check if user has sufficient tokens
    has_sufficient_tokens = available_tokens >= validation_result.tokens_required

    # Add token insufficiency error if needed
    errors = list(validation_result.errors)
    if not has_sufficient_tokens and validation_result.valid_count > 0:
        errors.append(CompanyCSVValidationError(
            row_number=0,  # Global error, not specific to a row
            field="tokens",
            error=f"Insufficient tokens. Required: {validation_result.tokens_required}, Available: {available_tokens}"
        ))

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
    """Import companies from CSV data.

    Consumes tokens from the organization's global token balance via global-service.
    Each company creation costs 35 tokens.

    Note: Per requirements, no token refunds on partial failures.
    """
    logger.info(
        f"CSV import request - User: {org_context.username}, "
        f"Rows: {len(import_request.companies)}"
    )

    verify_company_modify_permission(org_context, "company.create")

    # First validate to get count of valid companies
    validation = service.validate_csv_companies(
        companies=import_request.companies,
        organization_id=org_context.organization_id,
        token_manager=None,
    )

    # Calculate total tokens needed (TOKENS_PER_COMPANY per company)
    tokens_needed = validation.valid_count * TOKENS_PER_COMPANY

    if tokens_needed > 0:
        # Consume tokens via global-service
        # InsufficientTokensError (402) and ModuleNotEnabledError (403) propagate directly
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

    # Import the companies
    # Note: Per requirements, no token refunds on partial failures
    result = service.import_csv_companies(
        companies=import_request.companies,
        owner_username=org_context.username,
        organization_id=org_context.organization_id,
        skip_invalid=import_request.skip_invalid,
    )

    return result
