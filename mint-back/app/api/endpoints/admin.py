"""
Admin endpoints requiring admin role
"""

from typing import List
from fastapi import APIRouter, Depends, HTTPException, status

from app.services.company import CompanyService
from app.core.dependencies import get_company_service, get_current_user
from app.core.security import verify_admin_access
from app.schemas.company import CompanyResponse
from app.schemas.user import TokenData

router = APIRouter(
    prefix="/admin",
    tags=["admin"]
)


@router.get("/companies", response_model=List[CompanyResponse])
async def get_all_companies_admin(
    service: CompanyService = Depends(get_company_service),
    current_user: TokenData = Depends(get_current_user)
):
    """Get all companies (admin only)"""
    verify_admin_access(current_user)
    return service.get_all_companies()  # No username filter for admin


@router.get("/companies/{company_id}", response_model=CompanyResponse)
async def get_company_admin(
    company_id: int,
    service: CompanyService = Depends(get_company_service),
    current_user: TokenData = Depends(get_current_user)
):
    """Get any company by ID (admin only)"""
    verify_admin_access(current_user)
    company = service.get_company(company_id)
    if not company:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail=f"Company with ID {company_id} not found"
        )
    return company


@router.delete("/companies/{company_id}", status_code=status.HTTP_204_NO_CONTENT)
async def delete_company_admin(
    company_id: int,
    service: CompanyService = Depends(get_company_service),
    current_user: TokenData = Depends(get_current_user)
):
    """Delete any company (admin only)"""
    verify_admin_access(current_user)
    success = service.delete_company(company_id)
    if not success:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail=f"Company with ID {company_id} not found"
        )
    return {"success": True}


@router.get("/users/{username}/companies", response_model=List[CompanyResponse])
async def get_user_companies_admin(
    username: str,
    service: CompanyService = Depends(get_company_service),
    current_user: TokenData = Depends(get_current_user)
):
    """Get companies for a specific user (admin only)"""
    verify_admin_access(current_user)
    return service.get_all_companies(username=username)