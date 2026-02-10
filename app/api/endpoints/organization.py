"""
Organization endpoints for Keycloak Organizations integration.

This module provides organization context endpoints:
- /current: Returns current organization from JWT
- /activities: Returns recent activities (companies and folders) from other users

Note: During Phase 2, companies and folders are still in screen-service,
so /activities makes internal API calls. After Phase 3 (Folders Domain),
folders will be queried locally while companies remain in screen-service.
"""
import httpx
from fastapi import APIRouter, Depends, HTTPException, status

from app.core.auth_middleware import GatewayUser, build_internal_headers
from app.core.keycloak import OIDCUser, idp
from app.core.logging_config import get_logger
from app.core.organization import OrganizationContext, get_user_organization
from app.proxy.client import get_proxy_client
from app.schemas.organization import ActivityResponse, OrganizationResponse

logger = get_logger(__name__)

router = APIRouter(tags=["organization"])


@router.get("/current", response_model=OrganizationResponse)
async def get_current_organization(
    org_context: OrganizationContext = Depends(get_user_organization),
):
    """
    Get current user's organization information.

    Returns basic organization context extracted from JWT token.
    Organization management is handled in Keycloak.
    """
    logger.info(
        "Get current organization",
        extra={
            "user": org_context.username,
            "organization_id": org_context.organization_id
        }
    )

    return OrganizationResponse(
        id=org_context.organization_id,
        name=org_context.organization_name
    )


@router.get("/activities", response_model=list[ActivityResponse])
async def get_organization_activities(
    org_context: OrganizationContext = Depends(get_user_organization),
    user: OIDCUser = Depends(idp.get_current_user(extra_fields=["organization"])),
) -> list[ActivityResponse]:
    """
    Get recent creation activities in the organization.

    Shows last 10 companies and folders created by other users in the same organization.

    NOTE: During Phase 2, both companies and folders are still in screen-service,
    so this endpoint makes an internal API call to screen-service to fetch activities.
    After Phase 3 (Folders Domain migration), folders will be queried locally from
    global_schema while companies will continue to be fetched from screen-service.

    The response includes:
    - Companies created by other users (filtered by folder access control)
    - Folders shared with the current user (created by other users)

    Security: Access control is enforced by screen-service based on folder permissions.
    """
    logger.info(
        "Get organization activities",
        extra={
            "user": org_context.username,
            "organization_id": org_context.organization_id
        }
    )

    try:
        # Extract organization from extra_fields (fastapi-keycloak puts custom claims there)
        organization_claim = user.extra_fields.get("organization") if user.extra_fields else None

        # Convert OIDCUser to GatewayUser for build_internal_headers
        gateway_user = GatewayUser(
            sub=user.sub,
            preferred_username=user.preferred_username,
            email=user.email,
            realm_access=user.realm_access,
            organization=organization_claim,
        )

        # Build internal JWT headers for service-to-service auth
        internal_headers = build_internal_headers(gateway_user)

        if not internal_headers:
            logger.error("Failed to build internal headers for activities request")
            raise HTTPException(
                status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
                detail="Failed to authenticate with backend service"
            )

        # Make internal API call to screen-service to get activities
        # Screen-service owns companies (and folders until Phase 3)
        client = await get_proxy_client()
        response = await client.get(
            "/api/activities",
            headers=internal_headers,
        )

        # Check if request was successful
        if response.status_code != 200:
            logger.error(
                "Screen-service activities request failed",
                extra={
                    "status_code": response.status_code,
                    "response": response.text
                }
            )
            raise HTTPException(
                status_code=response.status_code,
                detail="Unable to retrieve activities from the backend service. Please try again later."
            )

        # Parse and return activities
        activities_data = response.json()
        activities = [ActivityResponse(**activity) for activity in activities_data]

        logger.debug(
            "Retrieved organization activities",
            extra={
                "count": len(activities),
                "user": org_context.username
            }
        )

        return activities

    except httpx.HTTPError as e:
        logger.error(
            "HTTP error calling screen-service for activities",
            extra={"error": str(e)}
        )
        raise HTTPException(
            status_code=status.HTTP_503_SERVICE_UNAVAILABLE,
            detail="Backend service unavailable"
        )
    except Exception as e:
        logger.error(
            "Unexpected error fetching activities",
            exc_info=True,  # This will log the full traceback
            extra={"error": str(e), "error_type": type(e).__name__}
        )
        raise HTTPException(
            status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
            detail="Unable to retrieve recent activities. Please try again later."
        )
