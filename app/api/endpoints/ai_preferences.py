"""AI Preferences API endpoints.

Provides REST endpoints for managing user AI preferences (Chapse Assist):
- GET /ai-preferences - Get current user's AI preferences
- POST /ai-preferences - Create or update AI preferences
"""

from fastapi import APIRouter, Depends, HTTPException, status

from app.core.dependencies import get_user_preferences_service
from app.core.logging_config import get_logger
from app.core.organization import get_user_organization, OrganizationContext
from app.schemas.ai_preferences import AiPreferencesCreate, AiPreferencesResponse
from app.services.user_preferences import UserPreferencesService

logger = get_logger(__name__)

router = APIRouter(prefix="/ai-preferences", tags=["ai-preferences"])


@router.get("", response_model=AiPreferencesResponse)
async def get_ai_preferences(
    org_context: OrganizationContext = Depends(get_user_organization),
    service: UserPreferencesService = Depends(get_user_preferences_service),
) -> AiPreferencesResponse:
    """Get current user's AI preferences.

    Args:
        org_context: Organization context with user_id from JWT.
        service: UserPreferencesService instance.

    Returns:
        AiPreferencesResponse with the user's AI preferences.

    Raises:
        HTTPException: 404 if preferences not found for this user.
    """
    ai_preferences = await service.get_ai_preferences(org_context.user_id)

    if not ai_preferences:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail="AI preferences not found for this user",
        )

    logger.info(
        "AI preferences retrieved",
        extra={
            "user_id": org_context.user_id,
            "username": org_context.username,
        },
    )

    return AiPreferencesResponse(**ai_preferences)


@router.post("", response_model=AiPreferencesResponse)
async def create_or_update_ai_preferences(
    preferences_data: AiPreferencesCreate,
    org_context: OrganizationContext = Depends(get_user_organization),
    service: UserPreferencesService = Depends(get_user_preferences_service),
) -> AiPreferencesResponse:
    """Create or update user's AI preferences.

    Performs an upsert: creates preferences if they don't exist,
    updates them if they do.

    Args:
        preferences_data: AI preferences payload.
        org_context: Organization context with user_id from JWT.
        service: UserPreferencesService instance.

    Returns:
        AiPreferencesResponse with the stored AI preferences.
    """
    ai_preferences = await service.set_ai_preferences(
        org_context.user_id,
        preferences_data.model_dump(),
    )

    return AiPreferencesResponse(**ai_preferences)
