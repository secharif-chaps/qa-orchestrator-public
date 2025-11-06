import logging
from typing import List
from fastapi import APIRouter, Depends, HTTPException, status, Query
from pydantic import ValidationError

logger = logging.getLogger(__name__)

from app.services.company import CompanyService
from app.services.token_manager import TokenManager
from app.core.dependencies import get_company_service, get_current_user, get_token_manager
from app.core.workspace import get_user_workspace, WorkspaceContext
from app.models.workspace import ModuleName
from app.core.security import verify_company_ownership, verify_company_workspace_access, verify_company_modify_permission
from app.schemas.company import (
    CompanyCreate, 
    CompanyUpdate, 
    CompanyResponse,
    CompanyCSVValidationRequest,
    CompanyCSVValidationResponse,
    CompanyCSVImportRequest,
    CompanyCSVImportResponse
)
from app.schemas.pagination import PaginationParams, PaginatedResponse, SortOrder
from app.schemas.chat import ChatRequest, ChatResponse
from app.schemas.user import TokenData
from app.infrastructure.dify.client import DifyClient

router = APIRouter(
    prefix="/companies",
    tags=["companies"]
)

@router.get("/recent", response_model=List[CompanyResponse])
async def get_recent_companies(
    limit: int = Query(5, ge=1, le=20, description="Number of recent companies to return"),
    service: CompanyService = Depends(get_company_service),
    workspace_context: WorkspaceContext = Depends(get_user_workspace)
):
    """Get recent companies with their folder information"""
    print(f"🏢 GET /companies/recent - User: {workspace_context.username}, Limit: {limit}")
    return service.get_recent_companies(
        workspace_id=workspace_context.workspace_id,
        limit=limit
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
    workspace_context: WorkspaceContext = Depends(get_user_workspace)
):
    """Get paginated companies for the current workspace"""
    effective_per_page = size if size is not None else per_page

    # Single line request log
    filter_info = f"name='{name}'" if name else "no filters"
    print(f"🏢 GET /companies - User: {workspace_context.username} - Page: {page}, Size: {effective_per_page}, {filter_info}")

    pagination_params = PaginationParams(
        page=page,
        per_page=effective_per_page,
        sort=sort,
        order=order
    )
    return service.get_paginated_companies(
        pagination_params,
        workspace_id=workspace_context.workspace_id,
        name_filter=name,
        include_archived=archived
    )

@router.get("/{company_id}", response_model=CompanyResponse)
async def get_company(
    company_id: int,
    service: CompanyService = Depends(get_company_service),
    workspace_context: WorkspaceContext = Depends(get_user_workspace)
):
    """Get a company by ID (only if it belongs to user's workspace)"""
    try:
        print(f"🏢 GET /api/companies/{company_id} - User: {workspace_context.username}, Workspace: {workspace_context.workspace_id}")
        company = service.get_company(company_id)
        if not company:
            print(f"❌ Company {company_id} not found")
            raise HTTPException(
                status_code=status.HTTP_404_NOT_FOUND,
                detail="Company not found"
            )
        print(f"✅ Company {company_id} found, verifying workspace access")
        return verify_company_workspace_access(company, workspace_context)
    except HTTPException:
        # Re-raise HTTP exceptions as-is
        raise
    except Exception as e:
        print(f"❌ Unexpected error in get_company: {str(e)}")
        logger.error(f"Unexpected error in get_company endpoint: {str(e)}", exc_info=True)
        raise HTTPException(
            status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
            detail="An internal server error occurred while retrieving the company"
        )

@router.get("/by-name/{name}", response_model=CompanyResponse)
async def get_company_by_name(
    name: str,
    service: CompanyService = Depends(get_company_service),
    workspace_context: WorkspaceContext = Depends(get_user_workspace)
):
    """Get a company by name (only if it belongs to user's workspace)"""
    # Input validation is handled by Pydantic models and path parameters
    company = service.get_company_by_name(name.strip())
    return verify_company_workspace_access(company, workspace_context)

@router.post("/", response_model=CompanyResponse)
async def create_company(
    company_data: CompanyCreate,
    service: CompanyService = Depends(get_company_service),
    token_manager: TokenManager = Depends(get_token_manager),
    workspace_context: WorkspaceContext = Depends(get_user_workspace)
):
    """Create a new company"""
    print(f"🏢 POST /api/companies/ - START - User: {workspace_context.username}, Data: {company_data.name[:50]}...")
    
    try:
        # Step 1: Consume token immediately (for 'screen' module - company creation/search/screening)
        print(f"🪙 Checking and consuming token for screen module in workspace: {workspace_context.workspace_id}")
        token_manager.consume_tokens(
            workspace_id=workspace_context.workspace_id,
            module_name=ModuleName.SCREEN,
            tokens=1
        )
        print(f"✅ Token consumed successfully")
        
        # Step 2: Input validation already handled by Pydantic CompanyCreate model
        print(f"📝 Processing company - Name: {company_data.name[:50]}, Website: {str(company_data.website)[:50]}")

        # Step 3: Create company using the authenticated user's username as the owner
        print(f"🔄 Calling service.create_company for authenticated user: {workspace_context.username} in workspace: {workspace_context.workspace_id}")
        result = service.create_company(
            name=company_data.name,
            website=str(company_data.website),
            owner_username=workspace_context.username,
            workspace_id=workspace_context.workspace_id
        )
        print(f"✅ Company created successfully - ID: {result.id}, Name: {result.name}")
        return result
        
    except ValidationError as e:
        print(f"❌ Validation error: {str(e)}")
        # Rollback token on validation failure
        try:
            token_manager.rollback_tokens(workspace_context.workspace_id, ModuleName.SCREEN, 1)
            print(f"🔄 Token rolled back due to validation error")
        except Exception as rollback_error:
            logger.error(f"Failed to rollback token: {rollback_error}")
        
        raise HTTPException(
            status_code=status.HTTP_400_BAD_REQUEST,
            detail=f"Validation error: {str(e)}"
        )
    except ValueError as e:
        print(f"❌ Value error: {str(e)}")
        # Rollback token on value error
        try:
            token_manager.rollback_tokens(workspace_context.workspace_id, ModuleName.SCREEN, 1)
            print(f"🔄 Token rolled back due to value error")
        except Exception as rollback_error:
            logger.error(f"Failed to rollback token: {rollback_error}")
        
        raise HTTPException(
            status_code=status.HTTP_400_BAD_REQUEST,
            detail=f"Invalid input: {str(e)}"
        )
    except Exception as e:
        print(f"❌ Unexpected error creating company: {str(e)}")
        logger.error(f"Unexpected error in create_company: {str(e)}", exc_info=True)
        
        # Rollback token on any other failure (but skip token-related errors)
        if not ("insufficient_tokens" in str(e) or "not enabled" in str(e)):
            try:
                token_manager.rollback_tokens(workspace_context.workspace_id, ModuleName.SCREEN, 1)
                print(f"🔄 Token rolled back due to unexpected error")
            except Exception as rollback_error:
                logger.error(f"Failed to rollback token: {rollback_error}")
        
        raise HTTPException(
            status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
            detail="An internal server error occurred while creating the company"
        )

@router.put("/{company_id}", response_model=CompanyResponse)
async def update_company(
    company_id: int,
    company_data: CompanyUpdate,
    service: CompanyService = Depends(get_company_service),
    workspace_context: WorkspaceContext = Depends(get_user_workspace)
):
    """Update a company (requires company.update permission)"""
    # Verify user has permission to update companies
    verify_company_modify_permission(workspace_context, "company.update")
    
    # Get existing company and verify it belongs to workspace
    company = service.get_company(company_id)
    verify_company_workspace_access(company, workspace_context)

    # Input validation already handled by Pydantic CompanyUpdate model

    # Update fields
    if company_data.name:
        company.name = company_data.name
    if company_data.website:
        company.website = company_data.website
    
    # Update complex fields if provided
    if company_data.profile:
        company.profile = company_data.profile
    if company_data.digital:
        company.digital = company_data.digital
    if company_data.timeline:
        company.timeline = company_data.timeline
    if company_data.products:
        company.products = company_data.products
    if company_data.jobs:
        company.jobs = company_data.jobs
    if company_data.csr:
        company.csr = company_data.csr
    if company_data.press:
        company.press = company_data.press
    if company_data.team:
        company.team = company_data.team
    
    # Save the updated company
    return service.update_company(company)

@router.delete("/{company_id}", response_model=CompanyResponse)
async def soft_delete_company(
    company_id: int,
    service: CompanyService = Depends(get_company_service),
    workspace_context: WorkspaceContext = Depends(get_user_workspace)
):
    """Soft delete a company (archive)"""
    
    # Verify user has permission to delete companies
    verify_company_modify_permission(workspace_context, "company.delete")
    
    # Get company and verify access
    company = service.get_company(company_id)
    if not company:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail="Company not found"
        )
    
    # Verify workspace access
    verify_company_workspace_access(company, workspace_context)
    
    # Soft delete the company
    deleted_company = service.soft_delete_company(company_id)
    if not deleted_company:
        raise HTTPException(
            status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
            detail="Failed to archive company"
        )
    
    return deleted_company

@router.post("/{company_id}/chatbot", response_model=ChatResponse)
async def chat_with_company(
    company_id: int,
    chat_request: ChatRequest,
    service: CompanyService = Depends(get_company_service),
    workspace_context: WorkspaceContext = Depends(get_user_workspace)
):
    """Chat with AI about a company using Dify workflow"""
    logger.info(f"Chat request for company {company_id} by user {workspace_context.username}")
    
    try:
        # Get company and verify it belongs to workspace
        company = service.get_company(company_id)
        verify_company_workspace_access(company, workspace_context)
        
        # Initialize Dify client
        dify_client = DifyClient()
        
        # Prepare company context for the chat
        company_context = {
            "id": company.id,
            "name": company.name,
            "website": company.website,
            "profile": company.profile,
            "digital": company.digital,
            "timeline": company.timeline,
            "products": company.products,
            "jobs": company.jobs,
            "csr": company.csr,
            "press": company.press,
            "team": company.team
        }
        
        # Convert chat history to simple format if provided
        chat_history = []
        if chat_request.chat_history:
            chat_history = [{"role": msg.role, "content": msg.content} for msg in chat_request.chat_history]
        
        # Send message to Dify
        response_data = await dify_client.send_chat_message(
            message=chat_request.message,
            company_context=company_context,
            chat_history=chat_history
        )
        
        return ChatResponse(
            response=response_data.get("response", "No response received"),
            status=response_data.get("status", "success")
        )
            
    except Exception as e:
        logger.error(f"Error in chat endpoint: {str(e)}")
        return ChatResponse(
            response="I'm sorry, I'm having trouble responding right now. Please try again in a moment.",
            status="error"
        )


@router.post("/{company_id}/restore", response_model=CompanyResponse)
async def restore_company(
    company_id: int,
    service: CompanyService = Depends(get_company_service),
    workspace_context: WorkspaceContext = Depends(get_user_workspace)
):
    """Restore a soft-deleted company"""
    print(f"🏢 POST /api/companies/{company_id}/restore - User: {workspace_context.username}")
    
    # Verify modification permission
    verify_company_modify_permission(workspace_context, 'company.update')
    
    # Restore the company
    restored_company = service.restore_company(company_id)
    if not restored_company:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail="Company not found or not deleted"
        )
    
    # Verify workspace access after restore
    return verify_company_workspace_access(restored_company, workspace_context)


@router.get("/archived/list", response_model=List[CompanyResponse])
async def get_archived_companies(
    service: CompanyService = Depends(get_company_service),
    workspace_context: WorkspaceContext = Depends(get_user_workspace)
):
    """Get all archived (soft-deleted) companies in the workspace"""
    print(f"🏢 GET /api/companies/archived/list - User: {workspace_context.username}")
    
    archived_companies = service.get_archived_companies(workspace_id=workspace_context.workspace_id)
    return archived_companies


@router.post("/csv/validate", response_model=CompanyCSVValidationResponse)
async def validate_csv_companies(
    validation_request: CompanyCSVValidationRequest,
    service: CompanyService = Depends(get_company_service),
    token_manager: TokenManager = Depends(get_token_manager),
    workspace_context: WorkspaceContext = Depends(get_user_workspace)
):
    """
    Validate CSV company data without creating companies.
    Also checks if user has sufficient tokens for valid companies.
    """
    logger.info(f"📋 CSV validation request - User: {workspace_context.username}, Rows: {len(validation_request.companies)}")
    
    # Verify user has permission to create companies
    verify_company_modify_permission(workspace_context, "company.create")
    
    # Validate companies and check token availability
    return service.validate_csv_companies(
        companies=validation_request.companies,
        workspace_id=workspace_context.workspace_id,
        token_manager=token_manager
    )


@router.post("/csv/import", response_model=CompanyCSVImportResponse)
async def import_csv_companies(
    import_request: CompanyCSVImportRequest,
    service: CompanyService = Depends(get_company_service),
    token_manager: TokenManager = Depends(get_token_manager),
    workspace_context: WorkspaceContext = Depends(get_user_workspace)
):
    """Import companies from CSV data"""
    logger.info(f"📋 CSV import request - User: {workspace_context.username}, Rows: {len(import_request.companies)}")
    
    # Verify user has permission to create companies
    verify_company_modify_permission(workspace_context, "company.create")
    
    # First validate to get token requirements
    validation = service.validate_csv_companies(
        companies=import_request.companies,
        workspace_id=workspace_context.workspace_id,
        token_manager=token_manager
    )
    
    # Check if we have sufficient tokens
    if not validation.has_sufficient_tokens:
        raise HTTPException(
            status_code=status.HTTP_402_PAYMENT_REQUIRED,
            detail=f"Insufficient tokens. Need {validation.tokens_required} tokens for {validation.valid_count} valid companies, but only {validation.tokens_available} available"
        )
    
    tokens_needed = validation.valid_count
    
    if tokens_needed > 0:
        try:
            # Consume tokens for all valid companies at once
            logger.info(f"🪙 Consuming {tokens_needed} tokens for CSV import")
            token_manager.consume_tokens(
                workspace_id=workspace_context.workspace_id,
                module_name=ModuleName.SCREEN,
                tokens=tokens_needed
            )
        except Exception as e:
            logger.error(f"Token consumption failed: {str(e)}")
            raise HTTPException(
                status_code=status.HTTP_402_PAYMENT_REQUIRED,
                detail=f"Failed to consume tokens: {str(e)}"
            )
    
    try:
        # Import the companies
        result = service.import_csv_companies(
            companies=import_request.companies,
            owner_username=workspace_context.username,
            workspace_id=workspace_context.workspace_id,
            skip_invalid=import_request.skip_invalid
        )
        
        # If some companies failed after token consumption, rollback the difference
        if result.failed > 0 and tokens_needed > result.successful:
            tokens_to_rollback = tokens_needed - result.successful
            try:
                token_manager.rollback_tokens(
                    workspace_context.workspace_id,
                    ModuleName.SCREEN,
                    tokens_to_rollback
                )
                logger.info(f"🔄 Rolled back {tokens_to_rollback} unused tokens")
            except Exception as rollback_error:
                logger.error(f"Failed to rollback tokens: {rollback_error}")
        
        return result
        
    except Exception as e:
        # On complete failure, rollback all tokens
        if tokens_needed > 0:
            try:
                token_manager.rollback_tokens(
                    workspace_context.workspace_id,
                    ModuleName.SCREEN,
                    tokens_needed
                )
            except Exception as rollback_error:
                logger.error(f"Failed to rollback tokens: {rollback_error}")
        raise 