"""Team management endpoints.

Provides team member management with permission tiers.
Route: /api/team/*

Permission Requirements:
- GET /members: organization.read (view team)
- GET /members/{user_id}/permissions: organization.read (get user permissions)
- PATCH /members/{user_id}: organization.manage OR admin.organizations (change permissions)
- POST /members/{user_id}/reset-password: organization.manage OR admin.organizations (reset password)
"""

from typing import Optional

from fastapi import APIRouter, Depends, HTTPException, Path, Query, status
from fastapi_keycloak import OIDCUser

from app.core.auth import verify_any_role_access
from app.core.keycloak import idp
from app.core.logging_config import get_logger
from app.core.organization import OrganizationContext, get_user_organization
from app.core.permissions import get_roles_for_tier, get_tier_from_roles
from app.schemas.team import (
    ResetPasswordRequest,
    TeamMember,
    TeamMemberListItem,
    TeamMemberListResponse,
    TeamMemberPasswordReset,
    TeamMemberPermissions,
    UpdateTeamMemberPermissions,
)
from app.services.keycloak_admin import keycloak_admin_service

router = APIRouter(prefix="/team", tags=["team"])
logger = get_logger(__name__)


@router.get("/members", response_model=TeamMemberListResponse)
async def list_team_members(
    page: int = Query(1, ge=1, description="Page number (1-indexed)"),
    limit: int = Query(10, ge=1, le=100, description="Items per page"),
    search: Optional[str] = Query(None, max_length=100, description="Search by name, email, username"),
    user: OIDCUser = Depends(idp.get_current_user(required_roles=["organization.read"])),
    org_context: OrganizationContext = Depends(get_user_organization),
):
    """List team members in the user's organization with pagination.

    Requires organization.read role for access.

    Returns team members WITHOUT permission tiers (use GET /members/{user_id}/permissions
    to fetch permissions on-demand for better performance).
    Current user is marked with is_current_user=true.

    Args:
        page: Page number (1-indexed)
        limit: Items per page (max 100)
        search: Optional search query for filtering members by name, email, or username
        user: Current authenticated user from Keycloak
        org_context: Organization context extracted from JWT

    Returns:
        Paginated list of team members (without permissions)

    Raises:
        500: If Keycloak API fails
    """
    try:
        # Calculate offset for Keycloak (0-indexed)
        first = (page - 1) * limit

        # Fetch members from Keycloak (single API call)
        keycloak_members = await keycloak_admin_service.get_organization_members(
            organization_id=org_context.organization_id,
            first=first,
            max_results=limit,
            search=search,
        )

        # Get total count for pagination
        total = await keycloak_admin_service.count_organization_members(org_context.organization_id)

        if not keycloak_members:
            logger.info(
                "No members found in organization",
                extra={"organization_id": org_context.organization_id, "search": search}
            )
            return TeamMemberListResponse(
                data=[],
                pagination={"total": 0, "page": page, "limit": limit, "total_pages": 0}
            )

        # Map to response format (NO permission fetching - done on-demand)
        team_members = []
        for member in keycloak_members:
            user_id = member.get('id')
            team_member = TeamMemberListItem(
                id=user_id,
                username=member.get('username', ''),
                email=member.get('email', ''),
                first_name=member.get('firstName'),
                last_name=member.get('lastName'),
                is_current_user=(user_id == user.sub),
                created_at=member.get('createdTimestamp'),
            )
            team_members.append(team_member)

        # Sort: current user first, then alphabetically
        team_members.sort(key=lambda m: (not m.is_current_user, m.username.lower()))

        # Calculate total pages
        total_pages = (total + limit - 1) // limit if total > 0 else 0

        logger.info(
            "Listed team members successfully",
            extra={
                "organization_id": org_context.organization_id,
                "member_count": len(team_members),
                "total": total,
                "page": page,
                "search_query": search,
            }
        )

        return TeamMemberListResponse(
            data=team_members,
            pagination={"total": total, "page": page, "limit": limit, "total_pages": total_pages}
        )

    except Exception as e:
        logger.error(
            "Failed to list team members",
            exc_info=True,
            extra={
                "organization_id": org_context.organization_id,
                "error_type": type(e).__name__,
                "error_message": str(e),
            }
        )
        raise HTTPException(
            status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
            detail="Failed to retrieve team members",
        )


@router.get("/members/{user_id}/permissions", response_model=TeamMemberPermissions)
async def get_member_permissions(
    user_id: str = Path(..., description="Keycloak user UUID"),
    user: OIDCUser = Depends(idp.get_current_user(required_roles=["organization.read"])),
    org_context: OrganizationContext = Depends(get_user_organization),
):
    """Get permission tier for a specific team member.

    Requires organization.read role for access.

    This endpoint is designed for lazy-loading permissions in the UI.
    Call this when the user opens the permissions dropdown.

    Args:
        user_id: Target user's Keycloak UUID
        user: Current authenticated user from Keycloak
        org_context: Organization context extracted from JWT

    Returns:
        User's permission tier

    Raises:
        404: If user not found
        500: If Keycloak API fails
    """
    try:
        # Fetch user roles from Keycloak
        user_roles_response = await keycloak_admin_service.get_user_realm_roles(user_id)

        if user_roles_response is None:
            raise HTTPException(
                status_code=status.HTTP_404_NOT_FOUND,
                detail="User not found",
            )

        user_roles = [role['name'] for role in user_roles_response] if user_roles_response else []
        permission_tier = get_tier_from_roles(user_roles)

        logger.info(
            "Fetched member permissions",
            extra={
                "organization_id": org_context.organization_id,
                "target_user_id": user_id,
                "permission_tier": permission_tier,
            }
        )

        return TeamMemberPermissions(
            user_id=user_id,
            permission_tier=permission_tier,
        )

    except HTTPException:
        raise
    except Exception as e:
        logger.error(
            "Failed to get member permissions",
            exc_info=True,
            extra={
                "user_id": user_id,
                "error_type": type(e).__name__,
                "error_message": str(e),
            }
        )
        raise HTTPException(
            status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
            detail="Failed to retrieve permissions",
        )


@router.patch("/members/{user_id}", response_model=TeamMember)
async def update_member_permissions(
    user_id: str = Path(..., description="Keycloak user UUID"),
    update_data: UpdateTeamMemberPermissions = ...,
    user: OIDCUser = Depends(idp.get_current_user()),
    org_context: OrganizationContext = Depends(get_user_organization),
):
    """Update team member permission tier.

    Requires organization.manage OR admin.organizations role for access.

    Atomically syncs all roles for the new tier in Keycloak.
    Prevents users from changing their own permissions.

    Args:
        user_id: Target user's Keycloak UUID
        update_data: New permission tier
        user: Current authenticated user from Keycloak
        org_context: Organization context extracted from JWT

    Returns:
        Updated team member with new tier

    Raises:
        400: If trying to change own permissions
        404: If user not found
        500: If Keycloak update fails
    """
    try:
        
        verify_any_role_access(user, ["organization.manage", "admin.organizations"])

        
        if user_id == user.sub:
            logger.warning(
                "User attempted to change own permissions",
                extra={"user_id": user_id, "organization_id": org_context.organization_id}
            )
            raise HTTPException(
                status_code=status.HTTP_400_BAD_REQUEST,
                detail="Cannot modify your own permissions",
            )

        
        keycloak_user = await keycloak_admin_service.get_user(user_id)
        if not keycloak_user:
            raise HTTPException(
                status_code=status.HTTP_404_NOT_FOUND,
                detail="User not found",
            )

        
        target_roles = get_roles_for_tier(update_data.permission_tier)

        
        
        success = await keycloak_admin_service.sync_user_realm_roles(
            user_id=user_id,
            target_roles=target_roles,
        )

        if not success:
            raise HTTPException(
                status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
                detail="Failed to update permissions in Keycloak",
            )

        updated_user_roles = await keycloak_admin_service.get_user_realm_roles(user_id)
        updated_roles = [role['name'] for role in updated_user_roles] if updated_user_roles else []

        team_member = TeamMember(
            id=user_id,
            username=keycloak_user.get('username', ''),
            email=keycloak_user.get('email', ''),
            first_name=keycloak_user.get('firstName'),
            last_name=keycloak_user.get('lastName'),
            avatar_url=None,
            permission_tier=get_tier_from_roles(updated_roles),
            is_current_user=False,
            created_at=keycloak_user.get('createdTimestamp'),
        )

        logger.info(
            "Updated team member permissions successfully",
            extra={
                "organization_id": org_context.organization_id,
                "target_user_id": user_id,
                "new_tier": update_data.permission_tier,
                "new_roles": target_roles,
                "updated_by": user.sub,
            }
        )

        return team_member

    except HTTPException:
        raise
    except Exception as e:
        logger.error(
            "Failed to update member permissions",
            exc_info=True,
            extra={
                "user_id": user_id,
                "new_tier": update_data.permission_tier,
                "error_type": type(e).__name__,
                "error_message": str(e),
            }
        )
        raise HTTPException(
            status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
            detail="Failed to update permissions",
        )


@router.post("/members/{user_id}/reset-password", response_model=TeamMemberPasswordReset)
async def reset_member_password(
    user_id: str = Path(..., description="Keycloak user UUID"),
    request: ResetPasswordRequest = None,
    user: OIDCUser = Depends(idp.get_current_user()),
    org_context: OrganizationContext = Depends(get_user_organization),
):
    """Reset team member password with temporary password.

    Requires organization.manage OR admin.organizations role for access.

    Sets password as temporary, forcing user to change it on next login.

    Args:
        user_id: Target user's Keycloak UUID
        request: Request body with temporary_password
        user: Current authenticated user from Keycloak
        org_context: Organization context extracted from JWT

    Returns:
        Temporary password and success message

    Raises:
        400: If password validation fails
        404: If user not found
        500: If password reset fails
    """
    try:
        verify_any_role_access(user, ["organization.manage", "admin.organizations"])

        keycloak_user = await keycloak_admin_service.get_user(user_id)
        if not keycloak_user:
            raise HTTPException(
                status_code=status.HTTP_404_NOT_FOUND,
                detail="User not found",
            )

        temp_password = request.temporary_password

        success = await keycloak_admin_service.set_user_password(
            user_id=user_id,
            password=temp_password,
            temporary=True,
        )

        if not success:
            raise HTTPException(
                status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
                detail="Failed to reset password in Keycloak",
            )

        logger.info(
            "Reset team member password successfully",
            extra={
                "organization_id": org_context.organization_id,
                "target_user_id": user_id,
                "target_username": keycloak_user.get('username'),
                "reset_by": user.sub,
            }
        )

        return TeamMemberPasswordReset(
            temporary_password=temp_password,
            message="Password reset successfully. User must change password on next login.",
        )

    except HTTPException:
        raise
    except Exception as e:
        logger.error(
            "Failed to reset member password",
            exc_info=True,
            extra={
                "user_id": user_id,
                "error_type": type(e).__name__,
                "error_message": str(e),
            }
        )
        raise HTTPException(
            status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
            detail="Failed to reset password",
        )
