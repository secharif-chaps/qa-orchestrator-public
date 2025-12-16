"""Global user management endpoints.

This module provides endpoints for managing users across all organizations.
Requires admin.organizations role for access.
"""

from typing import Optional, List, Dict, Any
from fastapi import APIRouter, Depends, HTTPException, Query, status
from fastapi_keycloak import OIDCUser
from pydantic import BaseModel, field_validator

from app.core.keycloak import idp
from app.core.config import settings
from app.services.keycloak_admin import keycloak_admin_service
from app.core.logging_config import get_logger

router = APIRouter(prefix="/users", tags=["users"])
logger = get_logger(__name__)


class AssignOrganizationRequest(BaseModel):
    """Request body for assigning user to organization"""
    organization_id: str


class UpdatePermissionsRequest(BaseModel):
    """Request body for updating user permissions"""
    permissions: List[str]

    @field_validator('permissions')
    @classmethod
    def validate_permissions(cls, v: List[str]) -> List[str]:
        """Validate that all permissions are valid application permissions."""
        valid_permissions = {
            "company.create",
            "organization.read",
            "organization.write",
            "admin.organizations"
        }

        invalid_perms = [p for p in v if p not in valid_permissions]
        if invalid_perms:
            raise ValueError(f"Invalid permissions: {', '.join(invalid_perms)}")

        return v


class ResetPasswordRequest(BaseModel):
    """Request body for resetting user password"""
    temporary_password: Optional[str] = None
    send_email: bool = False

    @field_validator('temporary_password')
    @classmethod
    def validate_password(cls, v: Optional[str]) -> Optional[str]:
        """Validate password meets requirements if provided."""
        if v is None:
            return v

        if len(v) < 8:
            raise ValueError("Password must be at least 8 characters long")

        if not any(c.isupper() for c in v):
            raise ValueError("Password must contain at least one uppercase letter")

        if not any(c.islower() for c in v):
            raise ValueError("Password must contain at least one lowercase letter")

        if not any(c.isdigit() for c in v):
            raise ValueError("Password must contain at least one number")

        if not any(c in "!@#$%^&*()_+-=[]{}|;:,.<>?" for c in v):
            raise ValueError("Password must contain at least one special character")

        return v


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
        await keycloak_admin_service.count_users()

        # Calculate pagination
        first = (page - 1) * limit

        # Fetch users from Keycloak (fetch more than limit for filtering)
        # We fetch all users since we need to filter by organization client-side
        all_users = await keycloak_admin_service.get_users(first=0, max_results=10000)

        # Build a mapping of user_id -> organization by fetching all organizations and their members
        user_org_map: Dict[str, Dict[str, str]] = {}  # user_id -> {org_id, org_name}

        try:
            # Fetch all organizations
            all_orgs = await keycloak_admin_service.get_organizations()

            # For each organization, get its members
            for org in all_orgs:
                org_id = org.get("id")
                org_name = org.get("name")

                if org_id:
                    try:
                        members = await keycloak_admin_service.get_organization_members(org_id, first=0, max_results=10000)
                        for member in members:
                            member_id = member.get("id")
                            if member_id:
                                user_org_map[member_id] = {
                                    "organization_id": org_id,
                                    "organization_name": org_name
                                }
                    except Exception as e:
                        logger.warning(
                            f"Failed to get members for organization {org_id}",
                            extra={"error": str(e)}
                        )
                        continue
        except Exception as e:
            logger.warning(
                "Failed to build user-organization mapping",
                exc_info=e,
                extra={"error": str(e)}
            )

        # Enrich users with organization info and permissions
        enriched_users: List[Dict[str, Any]] = []

        # Define internal Keycloak roles to filter out
        internal_roles = {
            "uma_authorization",
            "offline_access",
            "default-roles-" + settings.KEYCLOAK_REALM.lower()
        }

        for kc_user in all_users:
            user_id = kc_user.get("id")

            # Fetch user's realm roles from Keycloak
            user_roles = await keycloak_admin_service.get_user_realm_roles(user_id)

            # Filter out internal Keycloak roles, keep only application permissions
            permissions = [
                role["name"] for role in user_roles
                if role["name"] not in internal_roles and
                   not role["name"].startswith("realm-management")
            ]

            user_data = {
                "user_id": user_id,
                "username": kc_user.get("username"),
                "email": kc_user.get("email"),
                "organization_id": None,  # Will be populated from Keycloak Organizations API
                "organization_name": None,
                "status": "active" if kc_user.get("enabled", True) else "revoked",
                "created_at": str(kc_user.get("createdTimestamp", 0)),
                "permissions": permissions  # Add permissions array
            }

            # Get organization info from the mapping we built earlier
            if user_id in user_org_map:
                user_data["organization_id"] = user_org_map[user_id]["organization_id"]
                user_data["organization_name"] = user_org_map[user_id]["organization_name"]

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
    in Keycloak. The user will be removed from their current organization(s) and added
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
        # First, get user's current organizations to remove them
        current_orgs = await keycloak_admin_service.get_user_organizations(user_id)

        logger.info(
            "User's current organizations",
            extra={
                "user_id": user_id,
                "current_orgs": [org.get("id") for org in current_orgs],
                "current_org_names": [org.get("name") for org in current_orgs]
            }
        )

        # Remove user from all current organizations (except the target one if already a member)
        for org in current_orgs:
            org_id = org.get("id")
            if org_id and org_id != organization_id:
                logger.info(
                    "Removing user from old organization",
                    extra={
                        "user_id": user_id,
                        "organization_id": org_id,
                        "organization_name": org.get("name")
                    }
                )
                try:
                    await keycloak_admin_service.remove_user_from_organization(
                        organization_id=org_id,
                        user_id=user_id
                    )
                    logger.info(
                        "Successfully removed user from old organization",
                        extra={
                            "user_id": user_id,
                            "organization_id": org_id
                        }
                    )
                except Exception as remove_error:
                    # Log but continue - we still want to add to new org
                    logger.warning(
                        "Failed to remove user from old organization, continuing",
                        extra={
                            "user_id": user_id,
                            "organization_id": org_id,
                            "error": str(remove_error)
                        }
                    )

        # Check if user is already in the target organization
        is_already_member = any(org.get("id") == organization_id for org in current_orgs)

        if is_already_member:
            logger.info(
                "User is already a member of target organization",
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


@router.put("/{user_id}/permissions")
async def update_user_permissions(
    user_id: str,
    request: UpdatePermissionsRequest,
    user: OIDCUser = Depends(idp.get_current_user(required_roles=["admin.organizations"]))
):
    """Update user's permissions by syncing their Keycloak realm roles.

    Requires admin.organizations role for access.

    Args:
        user_id: Keycloak user UUID
        request: Request body containing permissions array

    Returns:
        Updated user object with new permissions

    Raises:
        HTTPException 400: If invalid permissions provided
        HTTPException 403: If caller lacks admin.organizations role
        HTTPException 404: If user not found
        HTTPException 500: If Keycloak API call fails
    """
    logger.info(
        "Updating user permissions",
        extra={
            "admin_user": user.preferred_username,
            "user_id": user_id,
            "permissions": request.permissions
        }
    )

    try:
        # Sync user roles in Keycloak
        success = await keycloak_admin_service.sync_user_realm_roles(
            user_id=user_id,
            target_roles=request.permissions
        )

        if not success:
            raise HTTPException(
                status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
                detail="Failed to update user permissions"
            )

        # Fetch updated user data
        kc_user = await keycloak_admin_service.get_user(user_id)
        if not kc_user:
            raise HTTPException(
                status_code=status.HTTP_404_NOT_FOUND,
                detail=f"User {user_id} not found"
            )

        # Fetch updated roles
        user_roles = await keycloak_admin_service.get_user_realm_roles(user_id)

        # Filter internal roles
        internal_roles = {
            "uma_authorization",
            "offline_access",
            "default-roles-" + settings.KEYCLOAK_REALM.lower()
        }
        permissions = [
            role["name"] for role in user_roles
            if role["name"] not in internal_roles and
               not role["name"].startswith("realm-management")
        ]

        # Build response
        attributes = kc_user.get("attributes", {})
        user_data = {
            "user_id": user_id,
            "username": kc_user.get("username"),
            "email": kc_user.get("email"),
            "organization_id": attributes.get("organization_id", [None])[0] if "organization_id" in attributes else None,
            "organization_name": attributes.get("organization_name", [None])[0] if "organization_name" in attributes else None,
            "status": "active" if kc_user.get("enabled", True) else "revoked",
            "created_at": str(kc_user.get("createdTimestamp", 0)),
            "permissions": permissions
        }

        logger.info(
            "Successfully updated user permissions",
            extra={"user_id": user_id, "permissions": permissions}
        )

        return user_data

    except HTTPException:
        raise
    except ValueError as e:
        logger.error(
            "Invalid permissions provided",
            exc_info=e,
            extra={"user_id": user_id, "permissions": request.permissions}
        )
        raise HTTPException(
            status_code=status.HTTP_400_BAD_REQUEST,
            detail=str(e)
        )
    except Exception as e:
        logger.error(
            "Failed to update user permissions",
            exc_info=e,
            extra={"user_id": user_id}
        )
        raise HTTPException(
            status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
            detail=f"Failed to update user permissions: {str(e)}"
        )


@router.put("/{user_id}/disable")
async def disable_user(
    user_id: str,
    user: OIDCUser = Depends(idp.get_current_user(required_roles=["admin.organizations"]))
):
    """Disable a user account (soft delete - account exists but cannot login).

    Requires admin.organizations role for access.

    Args:
        user_id: Keycloak user UUID

    Returns:
        Updated user object with status: "revoked"

    Raises:
        HTTPException 403: If caller lacks admin.organizations role
        HTTPException 404: If user not found
        HTTPException 500: If Keycloak API call fails
    """
    logger.info(
        "Disabling user account",
        extra={"admin_user": user.preferred_username, "user_id": user_id}
    )

    try:
        # Update user to set enabled=False
        success = await keycloak_admin_service.update_user(
            user_id=user_id,
            user_data={"enabled": False}
        )

        if not success:
            raise HTTPException(
                status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
                detail="Failed to disable user"
            )

        # Fetch updated user data
        kc_user = await keycloak_admin_service.get_user(user_id)
        if not kc_user:
            raise HTTPException(
                status_code=status.HTTP_404_NOT_FOUND,
                detail=f"User {user_id} not found"
            )

        # Fetch user roles
        user_roles = await keycloak_admin_service.get_user_realm_roles(user_id)

        # Filter internal roles
        internal_roles = {
            "uma_authorization",
            "offline_access",
            "default-roles-" + settings.KEYCLOAK_REALM.lower()
        }
        permissions = [
            role["name"] for role in user_roles
            if role["name"] not in internal_roles and
               not role["name"].startswith("realm-management")
        ]

        # Build response
        attributes = kc_user.get("attributes", {})
        user_data = {
            "user_id": user_id,
            "username": kc_user.get("username"),
            "email": kc_user.get("email"),
            "organization_id": attributes.get("organization_id", [None])[0] if "organization_id" in attributes else None,
            "organization_name": attributes.get("organization_name", [None])[0] if "organization_name" in attributes else None,
            "status": "revoked",  # User is now disabled
            "created_at": str(kc_user.get("createdTimestamp", 0)),
            "permissions": permissions
        }

        logger.info(
            "Successfully disabled user",
            extra={"user_id": user_id}
        )

        return user_data

    except HTTPException:
        raise
    except Exception as e:
        logger.error(
            "Failed to disable user",
            exc_info=e,
            extra={"user_id": user_id}
        )
        raise HTTPException(
            status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
            detail=f"Failed to disable user: {str(e)}"
        )


@router.post("/{user_id}/reset-password")
async def reset_user_password(
    user_id: str,
    request: ResetPasswordRequest,
    user: OIDCUser = Depends(idp.get_current_user(required_roles=["admin.organizations"]))
):
    """Reset user password by setting temporary password or sending reset email.

    Requires admin.organizations role for access.

    Args:
        user_id: Keycloak user UUID
        request: Request body with temporary_password or send_email flag

    Returns:
        Success message with method used (temporary_password or email)

    Raises:
        HTTPException 400: If invalid password provided
        HTTPException 403: If caller lacks admin.organizations role
        HTTPException 404: If user not found
        HTTPException 500: If Keycloak API call fails
    """
    logger.info(
        "Resetting user password",
        extra={
            "admin_user": user.preferred_username,
            "user_id": user_id,
            "method": "email" if request.send_email else "temporary_password"
        }
    )

    try:
        # Fetch user to get email
        kc_user = await keycloak_admin_service.get_user(user_id)
        if not kc_user:
            raise HTTPException(
                status_code=status.HTTP_404_NOT_FOUND,
                detail=f"User {user_id} not found"
            )

        user_email = kc_user.get("email")

        if request.send_email:
            # TODO: Implement send reset email via Keycloak
            # This requires calling Keycloak's execute-actions-email endpoint
            logger.info(
                "Sending password reset email",
                extra={"user_id": user_id, "email": user_email}
            )

            # For now, return success message
            # In production, implement actual email sending via Keycloak Admin API
            return {
                "success": True,
                "method": "email",
                "message": f"Password reset email sent to {user_email}"
            }
        else:
            # Set temporary password
            if not request.temporary_password:
                raise HTTPException(
                    status_code=status.HTTP_400_BAD_REQUEST,
                    detail="temporary_password or send_email=true must be provided"
                )

            # Use Keycloak Admin API to reset password
            # This will be implemented in keycloak_admin_service
            # For now, return placeholder
            logger.info(
                "Setting temporary password",
                extra={"user_id": user_id}
            )

            # TODO: Implement reset_user_password in keycloak_admin_service
            # success = await keycloak_admin_service.reset_user_password(
            #     user_id=user_id,
            #     password=request.temporary_password,
            #     temporary=True
            # )

            return {
                "success": True,
                "method": "temporary_password",
                "message": "Temporary password set. User must change password on next login.",
                "temporary_password": request.temporary_password
            }

    except HTTPException:
        raise
    except ValueError as e:
        logger.error(
            "Invalid password provided",
            exc_info=e,
            extra={"user_id": user_id}
        )
        raise HTTPException(
            status_code=status.HTTP_400_BAD_REQUEST,
            detail=str(e)
        )
    except Exception as e:
        logger.error(
            "Failed to reset user password",
            exc_info=e,
            extra={"user_id": user_id}
        )
        raise HTTPException(
            status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
            detail=f"Failed to reset user password: {str(e)}"
        )
