"""Global user management endpoints.

This module provides endpoints for managing users across all organizations.
Requires admin.organizations role for access.
"""

from typing import Optional, List, Dict, Any
from fastapi import APIRouter, Depends, HTTPException, Query, status
from fastapi_keycloak import OIDCUser
from pydantic import BaseModel

from app.core.keycloak import idp
from app.services.keycloak_admin import keycloak_admin_service
from app.core.logging_config import get_logger

router = APIRouter(prefix="/users", tags=["users"])
logger = get_logger(__name__)


class AssignOrganizationRequest(BaseModel):
    """Request body for assigning user to organization"""
    organization_id: str


@router.get("")
async def get_all_users(
    page: int = Query(1, ge=1, description="Page number (starting from 1)"),
    limit: int = Query(20, ge=1, le=100, description="Items per page (max 100)"),
    search: Optional[str] = Query(None, description="Search by username or email"),
    organization_filter: Optional[str] = Query(None, description="Filter by organization ID or 'none' for unassigned users"),
    sort: str = Query('created_at', description="Sort field: username, organization, created_at"),
    order: str = Query('desc', description="Sort order: asc or desc"),
    user: OIDCUser = Depends(idp.get_current_user(required_roles=["admin.organizations"]))
):
    """Get all users across all organizations with filtering and sorting.

    Requires admin.organizations role for access.

    Args:
        page: Page number (1-indexed)
        limit: Items per page (max 100)
        search: Search by username or email
        organization_filter: Filter by organization ID or 'none' for unassigned users
        sort: Field to sort by (username, organization, created_at)
        order: Sort order (asc or desc)

    Returns:
        Paginated list of users from Keycloak with organization info

    Raises:
        HTTPException 500: If Keycloak API call fails
    """
    logger.info(
        "Listing all users",
        extra={
            "admin_user": user.preferred_username,
            "page": page,
            "limit": limit,
            "search": search,
            "organization_filter": organization_filter
        }
    )

    try:
        # Get total count of users
        total_users = await keycloak_admin_service.count_users()

        # Calculate pagination
        first = (page - 1) * limit

        # Fetch users from Keycloak (fetch more than limit for filtering)
        # We fetch all users since we need to filter by organization client-side
        all_users = await keycloak_admin_service.get_users(first=0, max_results=10000)

        # TODO: Implement proper organization membership fetching
        # For now, we'll enrich users with organization info by checking their attributes
        enriched_users: List[Dict[str, Any]] = []

        for kc_user in all_users:
            user_data = {
                "user_id": kc_user.get("id"),
                "username": kc_user.get("username"),
                "email": kc_user.get("email"),
                "organization_id": None,  # Will be populated from user attributes or memberships
                "organization_name": None,
                "status": "active" if kc_user.get("enabled", True) else "revoked",
                "created_at": str(kc_user.get("createdTimestamp", 0))
            }

            # Extract organization from user attributes if available
            attributes = kc_user.get("attributes", {})
            if "organization_id" in attributes:
                org_ids = attributes["organization_id"]
                if isinstance(org_ids, list) and len(org_ids) > 0:
                    user_data["organization_id"] = org_ids[0]

            if "organization_name" in attributes:
                org_names = attributes["organization_name"]
                if isinstance(org_names, list) and len(org_names) > 0:
                    user_data["organization_name"] = org_names[0]

            enriched_users.append(user_data)

        # Apply search filter
        if search:
            search_lower = search.lower()
            enriched_users = [
                u for u in enriched_users
                if (u["username"] and search_lower in u["username"].lower()) or
                   (u["email"] and search_lower in u["email"].lower())
            ]

        # Apply organization filter
        if organization_filter:
            if organization_filter.lower() == "none":
                enriched_users = [u for u in enriched_users if not u["organization_id"]]
            else:
                enriched_users = [u for u in enriched_users if u["organization_id"] == organization_filter]

        # Sort users
        reverse = order.lower() == "desc"
        if sort == "username":
            enriched_users.sort(key=lambda u: u["username"] or "", reverse=reverse)
        elif sort == "organization":
            enriched_users.sort(key=lambda u: u["organization_name"] or "", reverse=reverse)
        elif sort == "created_at":
            enriched_users.sort(key=lambda u: int(u["created_at"]), reverse=reverse)

        # Calculate total after filtering
        total_filtered = len(enriched_users)

        # Apply pagination
        start_idx = first
        end_idx = start_idx + limit
        paginated_users = enriched_users[start_idx:end_idx]

        # Calculate pagination metadata
        total_pages = (total_filtered + limit - 1) // limit  # Ceiling division

        logger.info(
            "Retrieved users successfully",
            extra={
                "total": total_filtered,
                "page": page,
                "returned": len(paginated_users)
            }
        )

        return {
            "data": paginated_users,
            "pagination": {
                "page": page,
                "limit": limit,
                "total": total_filtered,
                "total_pages": total_pages
            }
        }

    except Exception as e:
        logger.error(
            "Failed to fetch users from Keycloak",
            exc_info=e,
            extra={"page": page, "limit": limit}
        )
        raise HTTPException(
            status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
            detail=f"Failed to fetch users: {str(e)}"
        )


@router.put("/{user_id}/organization")
async def assign_user_to_organization(
    user_id: str,
    request: AssignOrganizationRequest,
    user: OIDCUser = Depends(idp.get_current_user(required_roles=["admin.organizations"]))
):
    """Assign a user to a different organization (organization admin only).

    This endpoint allows organization administrators to move users between organizations
    in Keycloak. The user will be removed from their current organization and added
    to the specified organization.

    Args:
        user_id: Keycloak user UUID (from JWT sub claim)
        request: Request body containing organization_id

    Returns:
        Success message with user and organization details

    Raises:
        HTTPException 400: If organization_id is not provided
        HTTPException 500: If Keycloak API call fails

    Requires admin.organizations role for access.
    """
    organization_id = request.organization_id

    logger.info(
        "Assigning user to organization",
        extra={
            "admin_user": user.preferred_username,
            "user_id": user_id,
            "organization_id": organization_id
        }
    )

    if not organization_id:
        raise HTTPException(
            status_code=status.HTTP_400_BAD_REQUEST,
            detail="organization_id parameter is required"
        )

    try:
        # Add user to the new organization using Keycloak Admin API
        success = await keycloak_admin_service.add_user_to_organization(
            organization_id=organization_id,
            user_id=user_id
        )

        if success:
            logger.info(
                "Successfully assigned user to organization",
                extra={
                    "user_id": user_id,
                    "organization_id": organization_id
                }
            )
            return {
                "success": True,
                "message": f"User {user_id} successfully assigned to organization {organization_id}",
                "user_id": user_id,
                "organization_id": organization_id
            }
        else:
            raise HTTPException(
                status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
                detail="Failed to assign user to organization"
            )

    except HTTPException:
        raise
    except Exception as e:
        logger.error(
            "Failed to assign user to organization",
            exc_info=e,
            extra={"user_id": user_id, "organization_id": organization_id}
        )
        raise HTTPException(
            status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
            detail=f"Failed to assign user to organization: {str(e)}"
        )
