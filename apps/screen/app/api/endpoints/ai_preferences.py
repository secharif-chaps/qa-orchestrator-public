"""
AI Preferences endpoints for Chapse Assist feature
"""

from fastapi import APIRouter, Depends, HTTPException, status
from sqlalchemy.orm import Session
from app.core.organization import get_user_organization, OrganizationContext
from app.core.dependencies import get_company_service
from app.database import get_db
from app.services.user_preferences import UserPreferencesService
from app.services.company import CompanyService
from app.services.company_section_service import read_all_section_data
from app.services.dify import DifyService
from app.schemas.ai_preferences import (
    AiPreferencesCreate,
    AiPreferencesResponse,
    QuickActionsRequest,
    QuickActionsResponse,
)
import logging

router = APIRouter(
    prefix="/ai-preferences",
    tags=["ai-preferences"]
)

logger = logging.getLogger(__name__)


@router.get("", response_model=AiPreferencesResponse)
async def get_ai_preferences(
    org_context: OrganizationContext = Depends(get_user_organization),
    db: Session = Depends(get_db)
):
    """Get current user's AI preferences"""
    service = UserPreferencesService(db)
    ai_preferences = service.get_ai_preferences(org_context.username)

    if not ai_preferences:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail="AI preferences not found for this user"
        )

    return ai_preferences


@router.post("", response_model=AiPreferencesResponse, status_code=status.HTTP_200_OK)
async def create_or_update_ai_preferences(
    preferences_data: AiPreferencesCreate,
    org_context: OrganizationContext = Depends(get_user_organization),
    db: Session = Depends(get_db)
):
    """Create or update user's AI preferences"""
    service = UserPreferencesService(db)

    # Create or update AI preferences (service handles upsert)
    ai_preferences = service.set_ai_preferences(
        org_context.username,
        preferences_data.model_dump()
    )

    return ai_preferences


@router.post("/quick-actions", response_model=QuickActionsResponse)
async def generate_quick_actions(
    request: QuickActionsRequest,
    org_context: OrganizationContext = Depends(get_user_organization),
    company_service: CompanyService = Depends(get_company_service),
    db: Session = Depends(get_db)
):
    """Generate quick actions for a company using AI"""
    logger.info(f"Quick actions request for company {request.company_id} by user {org_context.username}")

    # 1. Get user's AI preferences
    preferences_service = UserPreferencesService(db)
    ai_preferences = preferences_service.get_ai_preferences(org_context.username)

    if not ai_preferences:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail="AI preferences not configured. Please set up your preferences first."
        )

    # 2. Get company data and verify organization access
    try:
        company = company_service.get_company(request.company_id)

        if company.organization_id != org_context.organization_id:
            logger.warning(f"User {org_context.username} attempted to access company {request.company_id} outside their organization")
            raise HTTPException(
                status_code=status.HTTP_403_FORBIDDEN,
                detail="Access denied to this company"
            )

    except HTTPException:
        # Re-raise HTTP exceptions (403, 404)
        raise
    except Exception as e:
        logger.error(f"Error fetching company {request.company_id}: {e}")
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail="Company not found"
        )

    # 3. Call Dify to generate quick actions
    try:
        dify_service = DifyService()

        # Prepare user preferences context
        user_context = {
            "role": ai_preferences.get("role"),
            "goals": ai_preferences.get("goals_text"),
            "desired_output": ai_preferences.get("desired_output_text"),
            "documentation": ai_preferences.get("documentation_text")
        }

        # Read section data from normalized tables
        section_data = read_all_section_data(db, company.id)

        # Prepare company context (all fields from normalized tables)
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
            "team": section_data.get("team", [])
        }

        # Generate actions via Dify
        result = await dify_service.generate_quick_actions(
            user_preferences=user_context,
            company_data=company_context
        )

        logger.info(f"Successfully generated quick actions for company {request.company_id}")
        return result

    except Exception as e:
        logger.error(f"Error generating quick actions: {e}", exc_info=True)
        raise HTTPException(
            status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
            detail="Failed to generate quick actions. Please try again or contact an administrator."
        )
