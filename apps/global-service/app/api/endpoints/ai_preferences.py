"""AI Preferences API endpoints.

Provides REST endpoints for managing user AI preferences (Chapse Assist):
- GET /ai-preferences - Get current user's AI preferences
- POST /ai-preferences - Create or update AI preferences
- POST /ai-preferences/quick-actions - Proxy quick actions generation to screen backend
"""

import json

from fastapi import APIRouter, Depends, HTTPException, Request, Response, status

from app.core.auth_middleware import auth_middleware
from app.core.dependencies import get_user_preferences_service
from app.core.logging_config import get_logger
from app.core.organization import OrganizationContext, get_user_organization
from app.proxy.client import get_proxy_client
from app.schemas.ai_preferences import AiPreferencesCreate, AiPreferencesResponse
from app.schemas.errors import COMMON_RESPONSES, NOT_FOUND_RESPONSE
from app.services.user_preferences import UserPreferencesService

logger = get_logger(__name__)

router = APIRouter(prefix="/ai-preferences", tags=["ai-preferences"])


@router.get(
    "",
    response_model=AiPreferencesResponse,
    summary="Get AI preferences",
    responses={**COMMON_RESPONSES, **NOT_FOUND_RESPONSE},
)
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


@router.post(
    "",
    response_model=AiPreferencesResponse,
    summary="Create or update AI preferences",
    responses={**COMMON_RESPONSES},
)
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


@router.post(
    "/quick-actions",
    summary="Generate quick actions",
    responses={**COMMON_RESPONSES, **NOT_FOUND_RESPONSE},
)
async def generate_quick_actions(
    request: Request,
    org_context: OrganizationContext = Depends(get_user_organization),
    service: UserPreferencesService = Depends(get_user_preferences_service),
) -> Response:
    """Proxy quick actions generation to screen backend.

    Fetches AI preferences from global-service DB and injects them
    into the request body before forwarding to screen backend.
    """
    # Fetch AI preferences from global-service DB
    ai_preferences = await service.get_ai_preferences(org_context.user_id)
    if not ai_preferences:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail="AI preferences not configured. Please set up your preferences first.",
        )

    # Parse original request body and inject AI preferences
    body = await request.body()
    payload = json.loads(body) if body else {}
    payload["ai_preferences"] = ai_preferences

    # Build internal headers for screen backend
    is_valid, user, internal_headers = await auth_middleware.validate_request(
        request, "ai-preferences/quick-actions"
    )

    if not is_valid:
        return Response(
            content=b'{"detail": "Not authenticated"}',
            status_code=401,
            media_type="application/json",
        )

    # Forward to screen backend with injected preferences
    client = await get_proxy_client()
    response = await client.post(
        "/api/ai-preferences/quick-actions",
        content=json.dumps(payload).encode(),
        headers={
            "Content-Type": "application/json",
            **internal_headers,
        },
    )

    return Response(
        content=response.content,
        status_code=response.status_code,
        media_type=response.headers.get("content-type"),
    )
