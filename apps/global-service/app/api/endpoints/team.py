"""Team management endpoints.

Provides team member management with permission tiers.
Route: /api/team/*

Ported from the backend monolith (back/app/api/endpoints/team.py).

Permission Requirements:
- POST /members: organization.read (invite member)
- GET /members: organization.read (view team)
- GET /members/{user_id}/permissions: organization.read (get user permissions)
- PUT /members/{user_id}: organization.manage OR admin.organizations (update profile & permissions)
- PATCH /members/{user_id}: organization.manage OR admin.organizations (change permissions only)
- DELETE /members/{user_id}: organization.manage OR admin.organizations (remove from team)
- POST /members/{user_id}/reset-password: organization.manage OR admin.organizations (reset password)
"""

from uuid import UUID

from fastapi import APIRouter, Depends, HTTPException, Path, Query, status

from app.core.authorization import verify_any_role_access
from app.core.keycloak import OIDCUser, idp
from app.core.logging_config import get_logger
from app.core.organization import OrganizationContext, get_user_organization
from app.core.permissions import PermissionTier, get_roles_for_tier, get_tier_from_roles
from app.schemas.errors import COMMON_RESPONSES, NOT_FOUND_RESPONSE
from app.schemas.team import (
    InviteTeamMemberRequest,
    InviteTeamMemberResponse,
    ResetPasswordRequest,
    TeamMember,
    TeamMemberListItem,
    TeamMemberListResponse,
    TeamMemberPasswordReset,
    TeamMemberPermissions,
    UpdateTeamMember,
    UpdateTeamMemberPermissions,
)
from app.services.keycloak_admin import keycloak_admin_service

router = APIRouter(prefix="/team", tags=["team"])
logger = get_logger(__name__)


@router.get(
    "/members",
    response_model=TeamMemberListResponse,
    summary="List team members",
    responses={**COMMON_RESPONSES},
)
async def list_team_members(
    page: int = Query(1, ge=1, description="Page number (1-indexed)"),
    limit: int = Query(10, ge=1, le=100, description="Items per page"),
    search: str | None = Query(None, max_length=100, description="Search by name, email, username"),
    user: OIDCUser = Depends(idp.get_current_user(required_roles=["organization.read"])),
    org_context: OrganizationContext = Depends(get_user_organization),
):
    """List team members in the user's organization with pagination.

    Returns members WITHOUT permission tiers (use GET /members/{user_id}/permissions
    to fetch permissions on-demand for better performance).
    """
    try:
        first = (page - 1) * limit

        keycloak_members = await keycloak_admin_service.get_organization_members(
            organization_id=org_context.organization_id,
            first=first,
            max_results=limit,
            search=search,
        )

        total = await keycloak_admin_service.count_organization_members(org_context.organization_id)

        if not keycloak_members:
            return TeamMemberListResponse(
                data=[],
                pagination={
                    "total": 0,
                    "page": page,
                    "limit": limit,
                    "total_pages": 0,
                },
            )

        team_members = [
            TeamMemberListItem(
                id=member.get("id"),
                username=member.get("username", ""),
                email=member.get("email", ""),
                first_name=member.get("firstName"),
                last_name=member.get("lastName"),
                is_current_user=(member.get("id") == user.sub),
                created_at=member.get("createdTimestamp"),
            )
            for member in keycloak_members
        ]

        # Sort: current user first, then alphabetically
        team_members.sort(key=lambda m: (not m.is_current_user, m.username.lower()))

        total_pages = (total + limit - 1) // limit if total > 0 else 0

        logger.info(
            "Listed team members",
            extra={
                "organization_id": org_context.organization_id,
                "member_count": len(team_members),
                "total": total,
                "page": page,
                "search_query": search,
            },
        )

        return TeamMemberListResponse(
            data=team_members,
            pagination={
                "total": total,
                "page": page,
                "limit": limit,
                "total_pages": total_pages,
            },
        )

    except Exception as e:
        logger.error(
            "Failed to list team members",
            exc_info=True,
            extra={
                "organization_id": org_context.organization_id,
                "error": str(e),
            },
        )
        raise HTTPException(
            status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
            detail={
                "error": "team_members_retrieval_failed",
                "message": "Failed to retrieve team members",
                "organization_id": org_context.organization_id,
            },
        )


@router.get(
    "/members/{user_id}/permissions",
    response_model=TeamMemberPermissions,
    summary="Get member permissions",
    responses={**COMMON_RESPONSES, **NOT_FOUND_RESPONSE},
)
async def get_member_permissions(
    user_id: UUID = Path(..., description="Keycloak user UUID"),
    user: OIDCUser = Depends(idp.get_current_user(required_roles=["organization.read"])),
    org_context: OrganizationContext = Depends(get_user_organization),
):
    """Get permission tier for a specific team member (lazy-loaded)."""
    try:
        uid = str(user_id)
        user_roles_response = await keycloak_admin_service.get_user_realm_roles(uid)

        if user_roles_response is None:
            raise HTTPException(
                status_code=status.HTTP_404_NOT_FOUND,
                detail={
                    "error": "user_not_found",
                    "message": "User not found",
                    "user_id": uid,
                },
            )

        role_names = [role["name"] for role in user_roles_response] if user_roles_response else []
        permission_tier = get_tier_from_roles(role_names)

        logger.info(
            "Fetched member permissions",
            extra={
                "organization_id": org_context.organization_id,
                "target_user_id": uid,
                "permission_tier": permission_tier.value,
            },
        )

        return TeamMemberPermissions(
            user_id=uid,
            permission_tier=permission_tier,
        )

    except HTTPException:
        raise
    except Exception as e:
        logger.error(
            "Failed to get member permissions",
            exc_info=True,
            extra={"user_id": str(user_id), "error": str(e)},
        )
        raise HTTPException(
            status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
            detail={
                "error": "permissions_retrieval_failed",
                "message": "Failed to retrieve permissions",
                "user_id": str(user_id),
            },
        )


# TODO: Frontend not implemented — invite member UI not yet built
@router.post(
    "/members",
    response_model=InviteTeamMemberResponse,
    status_code=status.HTTP_201_CREATED,
    summary="Invite a new team member",
    responses={**COMMON_RESPONSES},
)
async def invite_team_member(
    invite_data: InviteTeamMemberRequest,
    user: OIDCUser = Depends(idp.get_current_user()),
    org_context: OrganizationContext = Depends(get_user_organization),
):
    """Invite a new member to the team.

    Only admin member can invite new members.
    Creates a Keycloak user, adds them to the caller's organization,
    and assigns the requested permission tier roles.
    """
    try:
        verify_any_role_access(user, ["organization.manage", "admin.organizations"])

        # 1. Create user in Keycloak
        new_user_id = await keycloak_admin_service.create_user(
            username=invite_data.username,
            email=invite_data.email,
            first_name=invite_data.first_name,
            last_name=invite_data.last_name,
            password=invite_data.temporary_password,
            temporary_password=True,
        )

        if not new_user_id:
            raise HTTPException(
                status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
                detail={
                    "error": "user_action_failed",
                    "message": "Failed to create user in Keycloak",
                    "user_id": new_user_id,
                },
            )

        # 2. Add user to the organization
        added = await keycloak_admin_service.add_user_to_organization(
            organization_id=org_context.organization_id,
            user_id=new_user_id,
        )

        if not added:
            raise HTTPException(
                status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
                detail={"error": "action_failed", "message": "User created but failed to add to organization"},
            )

        # 3. Assign permission tier roles
        target_roles = get_roles_for_tier(invite_data.permission_tier)
        await keycloak_admin_service.sync_user_realm_roles(
            user_id=new_user_id,
            target_roles=target_roles,
        )

        logger.info(
            "Invited new team member",
            extra={
                "organization_id": org_context.organization_id,
                "new_user_id": new_user_id,
                "username": invite_data.username,
                "permission_tier": invite_data.permission_tier.value,
                "invited_by": user.sub,
            },
        )

        return InviteTeamMemberResponse(
            id=new_user_id,
            username=invite_data.username,
            email=invite_data.email,
            first_name=invite_data.first_name,
            last_name=invite_data.last_name,
            permission_tier=invite_data.permission_tier,
            temporary_password=invite_data.temporary_password,
        )

    except HTTPException:
        raise
    except Exception as e:
        logger.error(
            "Failed to invite team member",
            exc_info=True,
            extra={
                "username": invite_data.username,
                "error": str(e),
            },
        )
        raise HTTPException(
            status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
            detail={
                "error": "invite_failed",
                "message": "Failed to invite team member",
                "username": invite_data.username,
            },
        )


@router.patch(
    "/members/{user_id}",
    response_model=TeamMember,
    summary="Update member permissions",
    responses={**COMMON_RESPONSES, **NOT_FOUND_RESPONSE},
)
async def update_member_permissions(
    user_id: UUID = Path(..., description="Keycloak user UUID"),
    update_data: UpdateTeamMemberPermissions = ...,
    user: OIDCUser = Depends(idp.get_current_user()),
    org_context: OrganizationContext = Depends(get_user_organization),
):
    """Update team member permission tier.

    Requires organization.manage OR admin.organizations role.
    Atomically syncs all roles for the new tier in Keycloak.
    Prevents users from changing their own permissions.
    """
    try:
        uid = str(user_id)
        verify_any_role_access(user, ["organization.manage", "admin.organizations"])

        # Only admins can assign the admin tier (prevent privilege escalation)
        if update_data.permission_tier == PermissionTier.ADMIN:
            verify_any_role_access(user, ["admin.organizations"])

        if uid == user.sub:
            raise HTTPException(
                status_code=status.HTTP_400_BAD_REQUEST,
                detail={
                    "error": "self_modification_forbidden",
                    "message": "Cannot modify your own permissions",
                    "user_id": uid,
                },
            )

        keycloak_user = await keycloak_admin_service.get_user(uid)
        if not keycloak_user:
            raise HTTPException(
                status_code=status.HTTP_404_NOT_FOUND,
                detail={
                    "error": "user_not_found",
                    "message": "User not found",
                    "user_id": uid,
                },
            )

        # Only admins can modify users who currently have admin tier
        current_roles = await keycloak_admin_service.get_user_realm_roles(uid)
        current_tier = get_tier_from_roles([r["name"] for r in current_roles] if current_roles else [])
        if current_tier == PermissionTier.ADMIN:
            verify_any_role_access(user, ["admin.organizations"])

        target_roles = get_roles_for_tier(update_data.permission_tier)

        success = await keycloak_admin_service.sync_user_realm_roles(
            user_id=uid,
            target_roles=target_roles,
        )

        if not success:
            raise HTTPException(
                status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
                detail={
                    "error": "permission_update_failed",
                    "message": "Failed to update permissions in Keycloak",
                    "user_id": uid,
                },
            )

        updated_user_roles = await keycloak_admin_service.get_user_realm_roles(uid)
        updated_roles = [role["name"] for role in updated_user_roles] if updated_user_roles else []

        team_member = TeamMember(
            id=uid,
            username=keycloak_user.get("username", ""),
            email=keycloak_user.get("email", ""),
            first_name=keycloak_user.get("firstName"),
            last_name=keycloak_user.get("lastName"),
            avatar_url=None,
            permission_tier=get_tier_from_roles(updated_roles),
            is_current_user=False,
            created_at=keycloak_user.get("createdTimestamp"),
        )

        logger.info(
            "Updated team member permissions",
            extra={
                "organization_id": org_context.organization_id,
                "target_user_id": uid,
                "new_tier": update_data.permission_tier.value,
                "new_roles": target_roles,
                "updated_by": user.sub,
            },
        )

        return team_member

    except HTTPException:
        raise
    except Exception as e:
        logger.error(
            "Failed to update member permissions",
            exc_info=True,
            extra={
                "user_id": str(user_id),
                "new_tier": update_data.permission_tier.value,
                "error": str(e),
            },
        )
        raise HTTPException(
            status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
            detail={
                "error": "permission_update_failed",
                "message": "Failed to update permissions",
                "user_id": str(user_id),
            },
        )


# TODO: Frontend not implemented — update member profile UI not yet built
@router.put(
    "/members/{user_id}",
    response_model=TeamMember,
    summary="Update team member",
    responses={**COMMON_RESPONSES, **NOT_FOUND_RESPONSE},
)
async def update_team_member(
    user_id: UUID = Path(..., description="Keycloak user UUID"),
    update_data: UpdateTeamMember = ...,
    user: OIDCUser = Depends(idp.get_current_user()),
    org_context: OrganizationContext = Depends(get_user_organization),
):
    """Update team member profile and/or permission tier.

    Requires organization.manage OR admin.organizations role.
    Updates profile fields in Keycloak and optionally syncs permission tier roles.
    Prevents users from modifying themselves.
    """
    try:
        uid = str(user_id)
        verify_any_role_access(user, ["organization.manage", "admin.organizations"])

        # Only admins can assign the admin tier (prevent privilege escalation)
        if update_data.permission_tier == PermissionTier.ADMIN:
            verify_any_role_access(user, ["admin.organizations"])

        if uid == user.sub:
            raise HTTPException(
                status_code=status.HTTP_400_BAD_REQUEST,
                detail={
                    "error": "self_modification_forbidden",
                    "message": "Cannot modify your own profile",
                    "user_id": uid,
                },
            )

        keycloak_user = await keycloak_admin_service.get_user(uid)
        if not keycloak_user:
            raise HTTPException(
                status_code=status.HTTP_404_NOT_FOUND,
                detail={
                    "error": "user_not_found",
                    "message": "User not found",
                    "user_id": uid,
                },
            )

        # Only admins can modify users who currently have admin tier
        current_roles = await keycloak_admin_service.get_user_realm_roles(uid)
        current_tier = get_tier_from_roles([r["name"] for r in current_roles] if current_roles else [])
        if current_tier == PermissionTier.ADMIN:
            verify_any_role_access(user, ["admin.organizations"])

        # Build profile update payload with only provided fields
        field_mapping = {
            "firstName": update_data.first_name,
            "lastName": update_data.last_name,
            "email": update_data.email,
        }
        profile_updates = {k: v for k, v in field_mapping.items() if v is not None}

        if profile_updates:
            success = await keycloak_admin_service.update_user(
                user_id=uid,
                user_data=profile_updates,
            )
            if not success:
                raise HTTPException(
                    status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
                    detail={
                        "error": "profile_update_failed",
                        "message": "Failed to update user profile in Keycloak",
                        "user_id": uid,
                    },
                )

        # Update permission tier if provided
        if update_data.permission_tier is not None:
            target_roles = get_roles_for_tier(update_data.permission_tier)
            success = await keycloak_admin_service.sync_user_realm_roles(
                user_id=uid,
                target_roles=target_roles,
            )
            if not success:
                raise HTTPException(
                    status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
                    detail={
                        "error": "permission_update_failed",
                        "message": "Failed to update permissions in Keycloak",
                        "user_id": uid,
                    },
                )

        # Re-fetch user to return updated data
        updated_keycloak_user = await keycloak_admin_service.get_user(uid)
        if not updated_keycloak_user:
            updated_keycloak_user = keycloak_user

        updated_user_roles = await keycloak_admin_service.get_user_realm_roles(uid)
        updated_roles = [role["name"] for role in updated_user_roles] if updated_user_roles else []

        team_member = TeamMember(
            id=uid,
            username=updated_keycloak_user.get("username", ""),
            email=updated_keycloak_user.get("email", ""),
            first_name=updated_keycloak_user.get("firstName"),
            last_name=updated_keycloak_user.get("lastName"),
            avatar_url=None,
            permission_tier=get_tier_from_roles(updated_roles),
            is_current_user=False,
            created_at=updated_keycloak_user.get("createdTimestamp"),
        )

        logger.info(
            "Updated team member",
            extra={
                "organization_id": org_context.organization_id,
                "target_user_id": uid,
                "profile_fields_updated": list(profile_updates.keys()),
                "permission_tier_updated": update_data.permission_tier is not None,
                "updated_by": user.sub,
            },
        )

        return team_member

    except HTTPException:
        raise
    except Exception as e:
        logger.error(
            "Failed to update team member",
            exc_info=True,
            extra={"user_id": str(user_id), "error": str(e)},
        )
        raise HTTPException(
            status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
            detail={
                "error": "member_update_failed",
                "message": "Failed to update team member",
                "user_id": str(user_id),
            },
        )


@router.post(
    "/members/{user_id}/reset-password",
    response_model=TeamMemberPasswordReset,
    summary="Reset member password",
    responses={**COMMON_RESPONSES, **NOT_FOUND_RESPONSE},
)
async def reset_member_password(
    user_id: UUID = Path(..., description="Keycloak user UUID"),
    request: ResetPasswordRequest = ...,
    user: OIDCUser = Depends(idp.get_current_user()),
    org_context: OrganizationContext = Depends(get_user_organization),
):
    """Reset team member password with temporary password.

    Requires organization.manage OR admin.organizations role.
    Sets password as temporary, forcing user to change it on next login.
    """
    try:
        uid = str(user_id)
        verify_any_role_access(user, ["organization.manage", "admin.organizations"])

        keycloak_user = await keycloak_admin_service.get_user(uid)
        if not keycloak_user:
            raise HTTPException(
                status_code=status.HTTP_404_NOT_FOUND,
                detail={
                    "error": "user_not_found",
                    "message": "User not found",
                    "user_id": uid,
                },
            )

        success = await keycloak_admin_service.set_user_password(
            user_id=uid,
            password=request.temporary_password,
            temporary=True,
        )

        if not success:
            raise HTTPException(
                status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
                detail={
                    "error": "password_reset_failed",
                    "message": "Failed to reset password in Keycloak",
                    "user_id": uid,
                },
            )

        logger.info(
            "Reset team member password",
            extra={
                "organization_id": org_context.organization_id,
                "target_user_id": uid,
                "target_username": keycloak_user.get("username"),
                "reset_by": user.sub,
            },
        )

        return TeamMemberPasswordReset(
            temporary_password=request.temporary_password,
        )

    except HTTPException:
        raise
    except Exception as e:
        logger.error(
            "Failed to reset member password",
            exc_info=True,
            extra={"user_id": str(user_id), "error": str(e)},
        )
        raise HTTPException(
            status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
            detail={
                "error": "password_reset_failed",
                "message": "Failed to reset password",
                "user_id": str(user_id),
            },
        )


# TODO: Frontend not implemented — remove member UI not yet built
@router.delete(
    "/members/{user_id}",
    status_code=status.HTTP_204_NO_CONTENT,
    summary="Remove team member",
    responses={**COMMON_RESPONSES, **NOT_FOUND_RESPONSE},
)
async def remove_team_member(
    user_id: UUID = Path(..., description="Keycloak user UUID"),
    user: OIDCUser = Depends(idp.get_current_user()),
    org_context: OrganizationContext = Depends(get_user_organization),
):
    """Remove a member from the team (organization).

    Requires organization.manage OR admin.organizations role.
    Removes the user from the Keycloak organization and strips all
    application roles. Prevents users from removing themselves.
    """
    try:
        uid = str(user_id)
        verify_any_role_access(user, ["organization.manage", "admin.organizations"])

        if uid == user.sub:
            raise HTTPException(
                status_code=status.HTTP_400_BAD_REQUEST,
                detail={
                    "error": "self_removal_forbidden",
                    "message": "Cannot remove yourself from the team",
                    "user_id": uid,
                },
            )

        keycloak_user = await keycloak_admin_service.get_user(uid)
        if not keycloak_user:
            raise HTTPException(
                status_code=status.HTTP_404_NOT_FOUND,
                detail={
                    "error": "user_not_found",
                    "message": "User not found",
                    "user_id": uid,
                },
            )

        # Remove user from the organization
        removed = await keycloak_admin_service.remove_user_from_organization(
            organization_id=org_context.organization_id,
            user_id=uid,
        )

        if not removed:
            raise HTTPException(
                status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
                detail={
                    "error": "user_action_failed",
                    "message": "Failed to remove user from organization",
                    "user_id": uid,
                },
            )

        # Strip all application roles so the user has no residual access
        await keycloak_admin_service.sync_user_realm_roles(
            user_id=uid,
            target_roles=[],
        )

        logger.info(
            "Removed team member",
            extra={
                "organization_id": org_context.organization_id,
                "target_user_id": uid,
                "target_username": keycloak_user.get("username"),
                "removed_by": user.sub,
            },
        )

    except HTTPException:
        raise
    except Exception as e:
        logger.error(
            "Failed to remove team member",
            exc_info=True,
            extra={"user_id": str(user_id), "error": str(e)},
        )
        raise HTTPException(
            status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
            detail={
                "error": "member_removal_failed",
                "message": "Failed to remove team member",
                "user_id": str(user_id),
            },
        )
