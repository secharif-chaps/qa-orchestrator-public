"""Team management endpoints.

Provides team member management with permission tiers.
Route: /api/team/*

Permission Requirements:
- GET /members: organization.read (view team)
- PATCH /members/{user_id}/permissions: organization.manage OR admin.organizations (change permissions)
- POST /members/{user_id}/reset-password: organization.manage OR admin.organizations (reset password)
"""

from typing import List, Optional
from fastapi import APIRouter, Depends, HTTPException, status, Query, Path
from fastapi_keycloak import OIDCUser

from app.core.keycloak import idp
from app.core.auth import verify_any_role_access
from app.core.logging_config import get_logger
from app.core.organization import get_user_organization, OrganizationContext
from app.core.permissions import get_roles_for_tier, get_tier_from_roles
from app.schemas.team import (
    TeamMember,
    UpdateTeamMemberPermissions,
    TeamMemberPasswordReset,
)
from app.services.keycloak_admin import keycloak_admin_service

router = APIRouter(prefix="/team", tags=["team"])
logger = get_logger(__name__)


@router.get("/members", response_model=List[TeamMember])
async def list_team_members(
    search: Optional[str] = Query(None, max_length=100, description="Search by name, email, username"),
    user: OIDCUser = Depends(idp.get_current_user(required_roles=["organization.read"])),
    org_context: OrganizationContext = Depends(get_user_organization),
):
    """List all team members in the user's organization.

    Requires organization.read role for access.

    Returns team members with their permission tiers calculated from Keycloak roles.
    Current user is marked with is_current_user=true and sorted first.

    Args:
        search: Optional search query for filtering members by name, email, or username
        user: Current authenticated user from Keycloak
        org_context: Organization context extracted from JWT

    Returns:
        List of team members with permission tiers

    Raises:
        500: If Keycloak API fails
    """
    try:
        keycloak_members = await keycloak_admin_service.get_organization_members(
            organization_id=org_context.organization_id,
            first=0,
            max_results=1000,  
            search=search,
        )

        if not keycloak_members:
            logger.info(
                "No members found in organization",
                extra={"organization_id": org_context.organization_id, "search": search}
            )
            return []

        team_members = []
        for member in keycloak_members:
            user_id = member.get('id')

            user_roles_response = await keycloak_admin_service.get_user_realm_roles(user_id)
            user_roles = [role['name'] for role in user_roles_response] if user_roles_response else []

            permission_tier = get_tier_from_roles(user_roles)

            team_member = TeamMember(
                id=user_id,
                username=member.get('username', ''),
                email=member.get('email', ''),
                first_name=member.get('firstName'),
                last_name=member.get('lastName'),
                avatar_url=None,  
                permission_tier=permission_tier,
                is_current_user=(user_id == user.sub),
                created_at=member.get('createdTimestamp'),
            )
            team_members.append(team_member)

        team_members.sort(key=lambda m: (not m.is_current_user, m.username.lower()))

        logger.info(
            "Listed team members successfully",
            extra={
                "organization_id": org_context.organization_id,
                "member_count": len(team_members),
                "search_query": search,
            }
        )

        return team_members

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


@router.patch("/members/{user_id}/permissions", response_model=TeamMember)
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
    user: OIDCUser = Depends(idp.get_current_user()),
    org_context: OrganizationContext = Depends(get_user_organization),
):
    """Reset team member password with temporary password.

    Requires organization.manage OR admin.organizations role for access.

    Generates secure temporary password and forces password change on next login.

    Args:
        user_id: Target user's Keycloak UUID
        user: Current authenticated user from Keycloak
        org_context: Organization context extracted from JWT

    Returns:
        Temporary password and success message

    Raises:
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

        
        import secrets
        import string
        alphabet = string.ascii_letters + string.digits + "!@#$%^&*"
        temp_password = ''.join(secrets.choice(alphabet) for _ in range(12))

        
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
