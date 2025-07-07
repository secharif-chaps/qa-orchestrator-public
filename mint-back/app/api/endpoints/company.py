import logging
from typing import List
from fastapi import APIRouter, Depends, HTTPException, status

logger = logging.getLogger(__name__)

from app.services.company import CompanyService
from app.core.dependencies import get_company_service, get_current_user
from app.core.security import verify_company_ownership, sanitize_input
from app.schemas.company import (
    CompanyCreate, 
    CompanyUpdate, 
    CompanyResponse
)
from app.schemas.user import TokenData

router = APIRouter(
    prefix="/companies",
    tags=["companies"]
)

@router.get("/", response_model=List[CompanyResponse])
async def get_companies(
    service: CompanyService = Depends(get_company_service),
    current_user: TokenData = Depends(get_current_user)
):
    """Get all companies for the current user"""
    return service.get_all_companies(username=current_user.username)

@router.get("/{company_id}", response_model=CompanyResponse)
async def get_company(
    company_id: int,
    service: CompanyService = Depends(get_company_service),
    current_user: TokenData = Depends(get_current_user)
):
    """Get a company by ID (only if user owns it)"""
    company = service.get_company(company_id)
    return verify_company_ownership(company, current_user)

@router.get("/by-name/{name}", response_model=CompanyResponse)
async def get_company_by_name(
    name: str,
    service: CompanyService = Depends(get_company_service),
    current_user: TokenData = Depends(get_current_user)
):
    """Get a company by name (only if user owns it)"""
    # Sanitize the name input
    sanitized_name = sanitize_input(name, max_length=100)
    company = service.get_company_by_name(sanitized_name)
    return verify_company_ownership(company, current_user)

@router.post("/", response_model=CompanyResponse)
async def create_company(
    company_data: CompanyCreate,
    service: CompanyService = Depends(get_company_service),
    current_user: TokenData = Depends(get_current_user)
):
    """Create a new company"""
    print(f"🏢 POST /api/companies/ - START - User: {current_user.username}, Data: {company_data.name[:50]}...")
    
    try:
        # Sanitize inputs
        print(f"🧹 Sanitizing inputs - Name: {company_data.name[:50]}, Website: {company_data.website[:50]}")
        sanitized_name = sanitize_input(company_data.name, max_length=100)
        sanitized_website = sanitize_input(company_data.website, max_length=255)
        print(f"✅ Sanitized - Name: {sanitized_name[:50]}, Website: {sanitized_website[:50]}")
        
        # Use the authenticated user's username as the owner
        print(f"🔄 Calling service.create_company for authenticated user: {current_user.username}")
        result = service.create_company(
            name=sanitized_name,
            website=sanitized_website,
            owner_username=current_user.username
        )
        print(f"✅ Company created successfully - ID: {result.id}, Name: {result.name}")
        return result
        
    except ValidationError as e:
        print(f"❌ Validation error: {str(e)}")
        raise HTTPException(
            status_code=status.HTTP_400_BAD_REQUEST,
            detail=f"Validation error: {str(e)}"
        )
    except ValueError as e:
        print(f"❌ Value error: {str(e)}")
        raise HTTPException(
            status_code=status.HTTP_400_BAD_REQUEST,
            detail=f"Invalid input: {str(e)}"
        )
    except Exception as e:
        print(f"❌ Unexpected error creating company: {str(e)}")
        logger.error(f"Unexpected error in create_company: {str(e)}", exc_info=True)
        raise HTTPException(
            status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
            detail="An internal server error occurred while creating the company"
        )

@router.put("/{company_id}", response_model=CompanyResponse)
async def update_company(
    company_id: int,
    company_data: CompanyUpdate,
    service: CompanyService = Depends(get_company_service),
    current_user: TokenData = Depends(get_current_user)
):
    """Update a company (only if user owns it)"""
    # Get existing company and verify ownership
    company = service.get_company(company_id)
    verify_company_ownership(company, current_user)
    
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
    current_user: TokenData = Depends(get_current_user)
):
    """Delete a company (only if user owns it)"""
    # Get existing company and verify ownership
    company = service.get_company(company_id)
    verify_company_ownership(company, current_user)
    
    success = service.delete_company(company_id)
    if not success:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail=f"Company with ID {company_id} not found"
        )
    return {"success": True} 