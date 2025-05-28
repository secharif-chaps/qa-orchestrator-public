from typing import List, Dict, Any
from fastapi import APIRouter, Depends, HTTPException, status, BackgroundTasks

from app.domain.services.company_service import CompanyService
from app.core.dependencies import get_company_service
from app.domain.entities.schema import (
    CompanyCreate, 
    CompanyUpdate, 
    CompanyResponse,
    N8nWorkflowRequest
)

router = APIRouter(
    prefix="/companies",
    tags=["companies"]
)

@router.get("/", response_model=List[CompanyResponse])
async def get_companies(
    service: CompanyService = Depends(get_company_service)
):
    """Get all companies"""
    return service.get_all_companies()

@router.get("/{company_id}", response_model=CompanyResponse)
async def get_company(
    company_id: int,
    service: CompanyService = Depends(get_company_service)
):
    """Get a company by ID"""
    company = service.get_company(company_id)
    if not company:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail=f"Company with ID {company_id} not found"
        )
    return company

@router.get("/by-name/{name}", response_model=CompanyResponse)
async def get_company_by_name(
    name: str,
    service: CompanyService = Depends(get_company_service)
):
    """Get a company by name"""
    company = service.get_company_by_name(name)
    if not company:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail=f"Company with name '{name}' not found"
        )
    return company

@router.post("/", response_model=CompanyResponse)
async def create_company(
    company_data: CompanyCreate,
    service: CompanyService = Depends(get_company_service)
):
    """Create a new company"""
    return service.create_company(
        name=company_data.name,
        website=company_data.website
    )

@router.put("/{company_id}", response_model=CompanyResponse)
async def update_company(
    company_id: int,
    company_data: CompanyUpdate,
    service: CompanyService = Depends(get_company_service)
):
    """Update a company"""
    # Get existing company
    company = service.get_company(company_id)
    if not company:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail=f"Company with ID {company_id} not found"
        )
    
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
    service: CompanyService = Depends(get_company_service)
):
    """Delete a company"""
    success = service.delete_company(company_id)
    if not success:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail=f"Company with ID {company_id} not found"
        )
    return {"success": True}

@router.post("/search")
async def search_company(
    company_data: CompanyCreate,
    service: CompanyService = Depends(get_company_service)
):
    """
    Start a search for company data
    This will trigger all n8n workflows to collect data about the company
    """
    company = await service.initiate_company_search(
        name=company_data.name,
        website=company_data.website
    )
    
    return {
        "message": f"Search initiated for company: {company.name}",
        "company_id": company.id
    }

@router.post("/{company_id}/query/{query_type}")
async def start_query(
    company_id: int,
    query_type: str,
    service: CompanyService = Depends(get_company_service)
):
    """
    Start a specific query for company data
    
    Args:
        company_id: ID of the company
        query_type: Type of data to query (profile, team, etc.)
    """
    # Check if query type is valid
    valid_query_types = ["profile", "digital", "timeline", "products", "press", "csr", "jobs", "team"]
    if query_type not in valid_query_types:
        raise HTTPException(
            status_code=status.HTTP_400_BAD_REQUEST,
            detail=f"Invalid query type: {query_type}. Valid types are: {', '.join(valid_query_types)}"
        )
    
    # Start the query
    result = await service.start_query(company_id, query_type)

    return {
        "message": f"Query '{query_type}' finished for company ID: {company_id}",
        "result": result
    } 