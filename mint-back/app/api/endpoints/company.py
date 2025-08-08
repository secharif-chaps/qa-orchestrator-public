import logging
from typing import List
from fastapi import APIRouter, Depends, HTTPException, status, Query
from pydantic import ValidationError

logger = logging.getLogger(__name__)

from app.services.company import CompanyService
from app.services.token_manager import TokenManager
from app.core.dependencies import get_company_service, get_current_user, get_n8n_client, get_token_manager
from app.core.workspace import get_user_workspace, WorkspaceContext
from app.models.workspace import ModuleName
from app.core.security import verify_company_ownership, verify_company_workspace_access, verify_company_modify_permission, sanitize_input
from app.schemas.company import (
    CompanyCreate, 
    CompanyUpdate, 
    CompanyResponse
)
from app.schemas.pagination import PaginationParams, PaginatedResponse, SortOrder
from app.schemas.chat import ChatRequest, ChatResponse
from app.schemas.user import TokenData
from app.infrastructure.n8n.client import N8nClient

router = APIRouter(
    prefix="/companies",
    tags=["companies"]
)

@router.get("/", response_model=PaginatedResponse[CompanyResponse])
async def get_companies(
    page: int = Query(1, ge=1, description="Page number (starting from 1)"),
    per_page: int = Query(10, ge=1, le=100, description="Items per page (max 100)"),
    size: int = Query(None, ge=1, le=100, description="Items per page (alias for per_page)"),
    sort: str = Query(None, description="Field to sort by (name, created_at)"),
    order: SortOrder = Query(SortOrder.DESC, description="Sort order"),
    name: str = Query(None, description="Filter companies by name (partial match)"),
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
        name_filter=name
    )

@router.get("/{company_id}", response_model=CompanyResponse)
async def get_company(
    company_id: int,
    service: CompanyService = Depends(get_company_service),
    workspace_context: WorkspaceContext = Depends(get_user_workspace)
):
    """Get a company by ID (only if it belongs to user's workspace)"""
    company = service.get_company(company_id)
    return verify_company_workspace_access(company, workspace_context)

@router.get("/by-name/{name}", response_model=CompanyResponse)
async def get_company_by_name(
    name: str,
    service: CompanyService = Depends(get_company_service),
    workspace_context: WorkspaceContext = Depends(get_user_workspace)
):
    """Get a company by name (only if it belongs to user's workspace)"""
    # Sanitize the name input
    sanitized_name = sanitize_input(name, max_length=100)
    company = service.get_company_by_name(sanitized_name)
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
        
        # Step 2: Sanitize inputs
        print(f"🧹 Sanitizing inputs - Name: {company_data.name[:50]}, Website: {company_data.website[:50]}")
        sanitized_name = sanitize_input(company_data.name, max_length=100)
        sanitized_website = sanitize_input(company_data.website, max_length=255)
        print(f"✅ Sanitized - Name: {sanitized_name[:50]}, Website: {sanitized_website[:50]}")
        
        # Step 3: Create company using the authenticated user's username as the owner
        print(f"🔄 Calling service.create_company for authenticated user: {workspace_context.username} in workspace: {workspace_context.workspace_id}")
        result = service.create_company(
            name=sanitized_name,
            website=sanitized_website,
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
    
    # Sanitize inputs if provided
    if company_data.name:
        company_data.name = sanitize_input(company_data.name, max_length=100)
    if company_data.website:
        company_data.website = sanitize_input(company_data.website, max_length=255)
    
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

@router.delete("/{company_id}", status_code=status.HTTP_204_NO_CONTENT)
async def delete_company(
    company_id: int,
    service: CompanyService = Depends(get_company_service),
    workspace_context: WorkspaceContext = Depends(get_user_workspace)
):
    """Delete a company (requires company.delete permission)"""
    # Verify user has permission to delete companies
    verify_company_modify_permission(workspace_context, "company.delete")
    
    # Get existing company and verify it belongs to workspace
    company = service.get_company(company_id)
    verify_company_workspace_access(company, workspace_context)
    
    success = service.delete_company(company_id)
    if not success:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail=f"Company with ID {company_id} not found"
        )
    return {"success": True}

@router.post("/{company_id}/chatbot", response_model=ChatResponse)
async def chat_with_company(
    company_id: int,
    chat_request: ChatRequest,
    service: CompanyService = Depends(get_company_service),
    n8n_client: N8nClient = Depends(get_n8n_client),
    workspace_context: WorkspaceContext = Depends(get_user_workspace)
):
    """Chat with AI about a company (accessible to all workspace members)"""
    logger.info(f"Chat request for company {company_id} by user {workspace_context.username}")
    
    try:
        # Get company and verify it belongs to workspace
        company = service.get_company(company_id)
        verify_company_workspace_access(company, workspace_context)
        
        # Send chat message to n8n workflow
        response = await n8n_client.send_chat_message(
            message=chat_request.message,
            company_context=chat_request.company_context,
            chat_history=[msg.dict() for msg in chat_request.chat_history]
        )
        
        logger.info(f"Chat response received for company {company_id}")
        
        # Return the response in the expected format
        if isinstance(response, dict) and "response" in response:
            return ChatResponse(
                response=response["response"],
                status=response.get("status", "success")
            )
        else:
            # Handle unexpected response format
            return ChatResponse(
                response=str(response),
                status="success"
            )
            
    except Exception as e:
        logger.error(f"Error in chat endpoint: {str(e)}")
        # Return error response instead of raising exception
        return ChatResponse(
            response="I'm sorry, I'm having trouble responding right now. Please try again in a moment.",
            status="error"
        ) 