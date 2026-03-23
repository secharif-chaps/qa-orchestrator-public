"""Global user management endpoints.

This module provides endpoints for managing users across all organizations.
Requires admin.organizations role for access.
"""

import asyncio
import time
from typing import Any

from fastapi import APIRouter, Depends, HTTPException, Query, status
from fastapi_keycloak import OIDCUser
from pydantic import BaseModel, field_validator

from app.core.config import settings
from app.core.email_utils import is_chapsvision_email
from app.core.keycloak import idp
from app.core.logging_config import get_logger
from app.core.permissions import get_tier_from_roles
from app.schemas.user_import import (
    BulkUserImportRequest,
    BulkUserImportResponse,
)
from app.services.keycloak_admin import keycloak_admin_service
from app.services.user_import import import_users_bulk

router = APIRouter(prefix="/users", tags=["users"])
logger = get_logger(__name__)

# Performance limits for organization-based user search
MAX_ORGS_TO_SEARCH = 10  # Maximum organizations to fetch members from
MAX_MEMBERS_PER_ORG = 100  # Maximum members to fetch per organization
MAX_CONCURRENT_ORG_REQUESTS = 5  # Maximum parallel Keycloak requests
MAX_TOTAL_USERS_FROM_ORGS = 500  # Stop fetching when this many users found


def _transform_kc_user(kc_user: dict[str, Any], organization: dict[str, Any] | None = None) -> dict[str, Any]:
    """Transform a Keycloak user dict into the API response format.

    Args:
        kc_user: Keycloak user dictionary
        organization: Optional organization dictionary with 'id' and 'name'

    Returns:
        User dict with all fields including organization data
    """
    return {
        "user_id": kc_user.get("id"),
        "username": kc_user.get("username"),
        "email": kc_user.get("email"),
        "first_name": kc_user.get("firstName"),
        "last_name": kc_user.get("lastName"),
        "status": "active" if kc_user.get("enabled", True) else "revoked",
        "created_at": str(kc_user.get("createdTimestamp", 0)),
        "organization_id": organization.get("id") if organization else None,
        "organization_name": organization.get("name") if organization else None,
    }


def _sort_users(users: list[dict[str, Any]], sort: str, order: str) -> None:
    """Sort users list in place by the given field and order."""
    reverse = order.lower() == "desc"
    if sort == "username":
        users.sort(key=lambda u: (u["username"] or "").lower(), reverse=reverse)
    elif sort == "created_at":
        users.sort(key=lambda u: int(u["created_at"]), reverse=reverse)


async def _search_users_with_org(
    search: str,
) -> list[dict[str, Any]]:
    """Search users by name/email/username AND by organization name.

    Runs Keycloak user search and organization search in parallel,
    then merges and deduplicates the results.

    Performance limits applied:
    - Max {MAX_ORGS_TO_SEARCH} organizations searched
    - Max {MAX_MEMBERS_PER_ORG} members fetched per organization
    - Max {MAX_CONCURRENT_ORG_REQUESTS} concurrent Keycloak requests
    - Stops early when {MAX_TOTAL_USERS_FROM_ORGS} users found from orgs
    """
    kc_users, matching_orgs = await asyncio.gather(
        keycloak_admin_service.search_users(search=search, first=0, max_results=500),
        keycloak_admin_service.search_organizations(search),
    )

    # Collect user IDs already found to avoid duplicates
    seen_user_ids = {u.get("id") for u in kc_users}

    # Fetch members from matching organizations with limits
    if matching_orgs:
        # Limit number of organizations to search
        orgs_to_search = [org for org in matching_orgs if org.get("id")][:MAX_ORGS_TO_SEARCH]

        if len(matching_orgs) > MAX_ORGS_TO_SEARCH:
            logger.warning(
                "Organization search truncated due to too many matches",
                extra={
                    "search": search,
                    "total_matching_orgs": len(matching_orgs),
                    "orgs_searched": MAX_ORGS_TO_SEARCH,
                },
            )

        if orgs_to_search:
            # Use semaphore for concurrency control
            semaphore = asyncio.Semaphore(MAX_CONCURRENT_ORG_REQUESTS)
            users_from_orgs = 0

            async def fetch_org_members(org: dict[str, Any]) -> list[dict[str, Any]]:
                async with semaphore:
                    return await keycloak_admin_service.get_organization_members(
                        org.get("id"), first=0, max_results=MAX_MEMBERS_PER_ORG
                    )

            org_member_results = await asyncio.gather(*[fetch_org_members(org) for org in orgs_to_search])

            for members in org_member_results:
                for member in members:
                    # Early termination if we have enough users from orgs
                    if users_from_orgs >= MAX_TOTAL_USERS_FROM_ORGS:
                        logger.warning(
                            "User search from organizations truncated",
                            extra={
                                "search": search,
                                "max_users_reached": MAX_TOTAL_USERS_FROM_ORGS,
                            },
                        )
                        break

                    member_id = member.get("id")
                    if member_id and member_id not in seen_user_ids:
                        seen_user_ids.add(member_id)
                        kc_users.append(member)
                        users_from_orgs += 1
                else:
                    continue
                break  # Break outer loop if inner loop was broken

    return kc_users


async def _list_users_paginated(
    first: int,
    limit: int,
) -> tuple[list[dict[str, Any]], int]:
    """List users without search using Keycloak's native pagination."""
    total = await keycloak_admin_service.count_users_with_search(None)
    kc_users = await keycloak_admin_service.search_users(search=None, first=first, max_results=limit)
    return kc_users, total


class AssignOrganizationRequest(BaseModel):
    """Request body for assigning user to organization"""

    organization_id: str


class UpdatePermissionsRequest(BaseModel):
    """Request body for updating user permissions"""

    permissions: list[str]

    @field_validator("permissions")
    @classmethod
    def validate_permissions(cls, v: list[str]) -> list[str]:
        """Validate that all permissions are valid application permissions."""
        valid_permissions = {
            "company.create",
            "organization.read",
            "organization.write",
            "organization.manage",
            "admin.organizations",
        }

        invalid_perms = [p for p in v if p not in valid_permissions]
        if invalid_perms:
            raise ValueError(f"Invalid permissions: {', '.join(invalid_perms)}")

        return v


class ResetPasswordRequest(BaseModel):
    """Request body for resetting user password"""

    temporary_password: str | None = None
    send_email: bool = False

    @field_validator("temporary_password")
    @classmethod
    def validate_password(cls, v: str | None) -> str | None:
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
    search: str | None = Query(None, description="Search by username, name, email or organization"),
    sort: str = Query("created_at", description="Sort field: username, created_at"),
    order: str = Query("desc", description="Sort order: asc or desc"),
    user: OIDCUser = Depends(idp.get_current_user(required_roles=["admin.organizations"])),
):
    """Get all users with search and pagination using Keycloak's native API.

    This endpoint searches across username, email, first name, last name (via
    Keycloak's native search) and organization name (by fetching members of
    matching organizations and merging results).

    Requires admin.organizations role for access.

    Args:
        page: Page number (1-indexed)
        limit: Items per page (max 100)
        search: Search by username, email, first name, last name, or organization name
        sort: Field to sort by (username, created_at)
        order: Sort order (asc or desc)

    Returns:
        Paginated list of users (without permissions or organization)

    Raises:
        HTTPException 500: If Keycloak API call fails
    """
    start_time = time.time()

    logger.info(
        "Listing all users",
        extra={
            "admin_user": user.preferred_username,
            "page": page,
            "limit": limit,
            "search": search,
            "sort": sort,
            "order": order,
        },
    )

    try:
        if search and search.strip():
            # Hybrid search: Keycloak native + organization members
            kc_users = await _search_users_with_org(search)
            total = len(kc_users)
        else:
            # No search: use Keycloak's native pagination directly
            first = (page - 1) * limit
            kc_users, total = await _list_users_paginated(first, limit)

        # Fetch permissions for all users in parallel to compute roles
        async def fetch_user_role(user_id: str) -> tuple[str, str | None]:
            """Fetch permissions and compute role tier for a single user."""
            try:
                user_roles = await keycloak_admin_service.get_user_realm_roles(user_id)
                internal_roles = {
                    "uma_authorization",
                    "offline_access",
                    "default-roles-" + settings.KEYCLOAK_REALM.lower(),
                }
                permissions = [
                    role["name"]
                    for role in user_roles
                    if role["name"] not in internal_roles and not role["name"].startswith("realm-management")
                ]
                tier = get_tier_from_roles(permissions)
                return user_id, tier.value
            except Exception as e:
                logger.warning("Failed to fetch permissions for user", extra={"user_id": user_id, "error": str(e)})
                return user_id, None

        # Fetch organization for a single user
        async def fetch_user_organization(user_id: str) -> tuple[str, dict[str, Any] | None]:
            """Fetch organization for a single user."""
            try:
                org = await keycloak_admin_service.get_user_organization_optimized(user_id)
                return user_id, org
            except Exception as e:
                logger.warning("Failed to fetch organization for user", extra={"user_id": user_id, "error": str(e)})
                return user_id, None

        # Fetch roles and organizations in parallel
        role_tasks = [fetch_user_role(kc_user.get("id")) for kc_user in kc_users]
        org_tasks = [fetch_user_organization(kc_user.get("id")) for kc_user in kc_users]
        role_results, org_results = await asyncio.gather(asyncio.gather(*role_tasks), asyncio.gather(*org_tasks))
        user_roles_map = dict(role_results)
        user_orgs_map = dict(org_results)

        # Transform Keycloak users to response format with permission_tier and organization
        users_data = [
            {
                **_transform_kc_user(u, organization=user_orgs_map.get(u.get("id"))),
                "permission_tier": user_roles_map.get(u.get("id")),
            }
            for u in kc_users
        ]
        _sort_users(users_data, sort, order)

        # Apply pagination for search results (already paginated for non-search)
        if search and search.strip():
            first_idx = (page - 1) * limit
            users_data = users_data[first_idx : first_idx + limit]

        # Calculate pagination metadata
        total_pages = (total + limit - 1) // limit if total > 0 else 1

        elapsed_time = time.time() - start_time
        logger.info(
            "Retrieved users successfully",
            extra={
                "total": total,
                "page": page,
                "returned": len(users_data),
                "elapsed_seconds": round(elapsed_time, 3),
            },
        )

        return {
            "data": users_data,
            "pagination": {"page": page, "limit": limit, "total": total, "total_pages": total_pages},
        }

    except Exception as e:
        logger.error(
            "Failed to fetch users from Keycloak", exc_info=e, extra={"page": page, "limit": limit, "search": search}
        )
        raise HTTPException(
            status_code=status.HTTP_500_INTERNAL_SERVER_ERROR, detail=f"Failed to fetch users: {str(e)}"
        )


@router.get("/{user_id}/permissions")
async def get_user_permissions(
    user_id: str, user: OIDCUser = Depends(idp.get_current_user(required_roles=["admin.organizations"]))
):
    """Get user's current permissions (realm roles).

    This endpoint fetches the user's realm roles from Keycloak and filters out
    internal Keycloak roles, returning only application-level permissions.

    Requires admin.organizations role for access.

    Args:
        user_id: Keycloak user UUID

    Returns:
        User permissions object with user_id, username, and permissions array

    Raises:
        HTTPException 404: If user not found
        HTTPException 500: If Keycloak API call fails
    """
    logger.info("Fetching user permissions", extra={"admin_user": user.preferred_username, "user_id": user_id})

    try:
        # Fetch user to verify they exist and get username
        kc_user = await keycloak_admin_service.get_user(user_id)
        if not kc_user:
            raise HTTPException(status_code=status.HTTP_404_NOT_FOUND, detail=f"User {user_id} not found")

        # Fetch user's realm roles from Keycloak
        user_roles = await keycloak_admin_service.get_user_realm_roles(user_id)

        # Define internal Keycloak roles to filter out
        internal_roles = {"uma_authorization", "offline_access", "default-roles-" + settings.KEYCLOAK_REALM.lower()}

        # Filter out internal Keycloak roles, keep only application permissions
        permissions = [
            role["name"]
            for role in user_roles
            if role["name"] not in internal_roles and not role["name"].startswith("realm-management")
        ]

        logger.info(
            "Successfully fetched user permissions",
            extra={"user_id": user_id, "username": kc_user.get("username"), "permissions": permissions},
        )

        return {"user_id": user_id, "username": kc_user.get("username"), "permissions": permissions}

    except HTTPException:
        raise
    except Exception as e:
        logger.error("Failed to fetch user permissions", exc_info=e, extra={"user_id": user_id})
        raise HTTPException(
            status_code=status.HTTP_500_INTERNAL_SERVER_ERROR, detail=f"Failed to fetch user permissions: {str(e)}"
        )


@router.get("/{user_id}/organization")
async def get_user_organization(
    user_id: str, user: OIDCUser = Depends(idp.get_current_user(required_roles=["admin.organizations"]))
):
    """Get user's current organization membership.

    This endpoint fetches the user's organization from Keycloak Organizations.
    Returns null for organization if the user is not a member of any organization.

    Requires admin.organizations role for access.

    Args:
        user_id: Keycloak user UUID

    Returns:
        User organization object with user_id, username, and organization (or null)

    Raises:
        HTTPException 404: If user not found
        HTTPException 500: If Keycloak API call fails
    """
    logger.info("Fetching user organization", extra={"admin_user": user.preferred_username, "user_id": user_id})

    try:
        # Fetch user to verify they exist and get username
        kc_user = await keycloak_admin_service.get_user(user_id)
        if not kc_user:
            raise HTTPException(status_code=status.HTTP_404_NOT_FOUND, detail=f"User {user_id} not found")

        # Fetch user's organization using optimized method
        organization = await keycloak_admin_service.get_user_organization_optimized(user_id)

        logger.info(
            "Successfully fetched user organization",
            extra={"user_id": user_id, "username": kc_user.get("username"), "organization": organization},
        )

        return {"user_id": user_id, "username": kc_user.get("username"), "organization": organization}

    except HTTPException:
        raise
    except Exception as e:
        logger.error("Failed to fetch user organization", exc_info=e, extra={"user_id": user_id})
        raise HTTPException(
            status_code=status.HTTP_500_INTERNAL_SERVER_ERROR, detail=f"Failed to fetch user organization: {str(e)}"
        )


@router.put("/{user_id}/organization")
async def assign_user_to_organization(
    user_id: str,
    request: AssignOrganizationRequest,
    user: OIDCUser = Depends(idp.get_current_user(required_roles=["admin.organizations"])),
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
        extra={"admin_user": user.preferred_username, "user_id": user_id, "organization_id": organization_id},
    )

    if not organization_id:
        raise HTTPException(status_code=status.HTTP_400_BAD_REQUEST, detail="organization_id parameter is required")

    try:
        # First, get user's current organizations to remove them
        current_orgs = await keycloak_admin_service.get_user_organizations(user_id)

        logger.info(
            "User's current organizations",
            extra={
                "user_id": user_id,
                "current_orgs": [org.get("id") for org in current_orgs],
                "current_org_names": [org.get("name") for org in current_orgs],
            },
        )

        # Remove user from all current organizations (except the target one if already a member)
        for org in current_orgs:
            org_id = org.get("id")
            if org_id and org_id != organization_id:
                logger.info(
                    "Removing user from old organization",
                    extra={"user_id": user_id, "organization_id": org_id, "organization_name": org.get("name")},
                )
                try:
                    await keycloak_admin_service.remove_user_from_organization(organization_id=org_id, user_id=user_id)
                    logger.info(
                        "Successfully removed user from old organization",
                        extra={"user_id": user_id, "organization_id": org_id},
                    )
                except Exception as remove_error:
                    # Log but continue - we still want to add to new org
                    logger.warning(
                        "Failed to remove user from old organization, continuing",
                        extra={"user_id": user_id, "organization_id": org_id, "error": str(remove_error)},
                    )

        # Check if user is already in the target organization
        is_already_member = any(org.get("id") == organization_id for org in current_orgs)

        if is_already_member:
            logger.info(
                "User is already a member of target organization",
                extra={"user_id": user_id, "organization_id": organization_id},
            )
            return {
                "success": True,
                "message": f"User {user_id} successfully assigned to organization {organization_id}",
                "user_id": user_id,
                "organization_id": organization_id,
            }

        # Add user to the new organization using Keycloak Admin API
        success = await keycloak_admin_service.add_user_to_organization(
            organization_id=organization_id, user_id=user_id
        )

        if success:
            logger.info(
                "Successfully assigned user to organization",
                extra={"user_id": user_id, "organization_id": organization_id},
            )
            return {
                "success": True,
                "message": f"User {user_id} successfully assigned to organization {organization_id}",
                "user_id": user_id,
                "organization_id": organization_id,
            }
        else:
            raise HTTPException(
                status_code=status.HTTP_500_INTERNAL_SERVER_ERROR, detail="Failed to assign user to organization"
            )

    except HTTPException:
        raise
    except Exception as e:
        logger.error(
            "Failed to assign user to organization",
            exc_info=e,
            extra={"user_id": user_id, "organization_id": organization_id},
        )
        raise HTTPException(
            status_code=status.HTTP_500_INTERNAL_SERVER_ERROR, detail=f"Failed to assign user to organization: {str(e)}"
        )


@router.put("/{user_id}/permissions")
async def update_user_permissions(
    user_id: str,
    request: UpdatePermissionsRequest,
    user: OIDCUser = Depends(idp.get_current_user(required_roles=["admin.organizations"])),
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
        HTTPException 403: If caller lacks admin.organizations role or non-ChapsVision user receives admin role
        HTTPException 404: If user not found
        HTTPException 500: If Keycloak API call fails
    """
    logger.info(
        "Updating user permissions",
        extra={"admin_user": user.preferred_username, "user_id": user_id, "permissions": request.permissions},
    )

    try:
        # Check if admin.organizations permission is being assigned
        if "admin.organizations" in request.permissions:
            # Fetch user email from Keycloak BEFORE validation
            kc_user = await keycloak_admin_service.get_user(user_id)
            if not kc_user:
                raise HTTPException(status_code=status.HTTP_404_NOT_FOUND, detail=f"User {user_id} not found")

            # Validate email domain for admin role
            user_email = kc_user.get("email")
            if not is_chapsvision_email(user_email):
                logger.warning(
                    "Unauthorized admin assignment attempt",
                    extra={"user_id": user_id, "email": user_email, "requester": user.preferred_username},
                )
                raise HTTPException(
                    status_code=status.HTTP_403_FORBIDDEN,
                    detail="Admin role can only be assigned to ChapsVision employees",
                )

        # Sync user roles in Keycloak
        success = await keycloak_admin_service.sync_user_realm_roles(user_id=user_id, target_roles=request.permissions)

        if not success:
            raise HTTPException(
                status_code=status.HTTP_500_INTERNAL_SERVER_ERROR, detail="Failed to update user permissions"
            )

        # Fetch updated user data
        kc_user = await keycloak_admin_service.get_user(user_id)
        if not kc_user:
            raise HTTPException(status_code=status.HTTP_404_NOT_FOUND, detail=f"User {user_id} not found")

        # Fetch updated roles
        user_roles = await keycloak_admin_service.get_user_realm_roles(user_id)

        # Filter internal roles
        internal_roles = {"uma_authorization", "offline_access", "default-roles-" + settings.KEYCLOAK_REALM.lower()}
        permissions = [
            role["name"]
            for role in user_roles
            if role["name"] not in internal_roles and not role["name"].startswith("realm-management")
        ]

        # Build response
        attributes = kc_user.get("attributes", {})
        user_data = {
            "user_id": user_id,
            "username": kc_user.get("username"),
            "email": kc_user.get("email"),
            "organization_id": attributes.get("organization_id", [None])[0]
            if "organization_id" in attributes
            else None,
            "organization_name": attributes.get("organization_name", [None])[0]
            if "organization_name" in attributes
            else None,
            "status": "active" if kc_user.get("enabled", True) else "revoked",
            "created_at": str(kc_user.get("createdTimestamp", 0)),
            "permissions": permissions,
        }

        logger.info("Successfully updated user permissions", extra={"user_id": user_id, "permissions": permissions})

        return user_data

    except HTTPException:
        raise
    except ValueError as e:
        logger.error(
            "Invalid permissions provided", exc_info=e, extra={"user_id": user_id, "permissions": request.permissions}
        )
        raise HTTPException(status_code=status.HTTP_400_BAD_REQUEST, detail=str(e))
    except Exception as e:
        logger.error("Failed to update user permissions", exc_info=e, extra={"user_id": user_id})
        raise HTTPException(
            status_code=status.HTTP_500_INTERNAL_SERVER_ERROR, detail=f"Failed to update user permissions: {str(e)}"
        )


async def _update_user_enabled_status(user_id: str, enabled: bool, admin_username: str) -> dict[str, Any]:
    """Update user enabled/disabled status in Keycloak.

    Shared helper function for enable_user and disable_user endpoints.

    Args:
        user_id: Keycloak user UUID
        enabled: True to enable, False to disable
        admin_username: Username of admin performing the action

    Returns:
        Updated user object with new status

    Raises:
        HTTPException 404: If user not found
        HTTPException 500: If Keycloak API call fails
    """
    action = "Enabling" if enabled else "Disabling"
    action_past = "enabled" if enabled else "disabled"
    status_value = "active" if enabled else "revoked"

    logger.info(f"{action} user account", extra={"admin_user": admin_username, "user_id": user_id})

    try:
        success = await keycloak_admin_service.update_user(user_id=user_id, user_data={"enabled": enabled})

        if not success:
            raise HTTPException(
                status_code=status.HTTP_500_INTERNAL_SERVER_ERROR, detail=f"Failed to {action.lower()} user"
            )

        kc_user = await keycloak_admin_service.get_user(user_id)
        if not kc_user:
            raise HTTPException(status_code=status.HTTP_404_NOT_FOUND, detail=f"User {user_id} not found")

        user_roles = await keycloak_admin_service.get_user_realm_roles(user_id)

        internal_roles = {"uma_authorization", "offline_access", "default-roles-" + settings.KEYCLOAK_REALM.lower()}
        permissions = [
            role["name"]
            for role in user_roles
            if role["name"] not in internal_roles and not role["name"].startswith("realm-management")
        ]

        attributes = kc_user.get("attributes", {})
        user_data = {
            "user_id": user_id,
            "username": kc_user.get("username"),
            "email": kc_user.get("email"),
            "organization_id": attributes.get("organization_id", [None])[0]
            if "organization_id" in attributes
            else None,
            "organization_name": attributes.get("organization_name", [None])[0]
            if "organization_name" in attributes
            else None,
            "status": status_value,
            "created_at": str(kc_user.get("createdTimestamp", 0)),
            "permissions": permissions,
        }

        logger.info(f"Successfully {action_past} user", extra={"user_id": user_id})

        return user_data

    except HTTPException:
        raise
    except Exception as e:
        logger.error(f"Failed to {action.lower()} user", exc_info=e, extra={"user_id": user_id})
        raise HTTPException(
            status_code=status.HTTP_500_INTERNAL_SERVER_ERROR, detail=f"Failed to {action.lower()} user: {str(e)}"
        )


@router.put("/{user_id}/disable")
async def disable_user(
    user_id: str, user: OIDCUser = Depends(idp.get_current_user(required_roles=["admin.organizations"]))
) -> dict[str, Any]:
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
    return await _update_user_enabled_status(user_id=user_id, enabled=False, admin_username=user.preferred_username)


@router.put("/{user_id}/enable")
async def enable_user(
    user_id: str, user: OIDCUser = Depends(idp.get_current_user(required_roles=["admin.organizations"]))
) -> dict[str, Any]:
    """Enable a previously disabled user account.

    Requires admin.organizations role for access.

    Args:
        user_id: Keycloak user UUID

    Returns:
        Updated user object with status: "active"

    Raises:
        HTTPException 403: If caller lacks admin.organizations role
        HTTPException 404: If user not found
        HTTPException 500: If Keycloak API call fails
    """
    return await _update_user_enabled_status(user_id=user_id, enabled=True, admin_username=user.preferred_username)


@router.post("/{user_id}/reset-password")
async def reset_user_password(
    user_id: str,
    request: ResetPasswordRequest,
    user: OIDCUser = Depends(idp.get_current_user(required_roles=["admin.organizations"])),
):
    """Reset user password by setting temporary password.

    Requires admin.organizations role for access.

    Note: Email-based password reset is not currently supported as email
    is not configured in Keycloak. Use temporary_password to set a new
    password directly.

    Args:
        user_id: Keycloak user UUID
        request: Request body with temporary_password

    Returns:
        Success message with the new temporary password

    Raises:
        HTTPException 400: If invalid password provided or password missing
        HTTPException 403: If caller lacks admin.organizations role
        HTTPException 404: If user not found
        HTTPException 500: If Keycloak API call fails
    """
    logger.info("Resetting user password", extra={"admin_user": user.preferred_username, "user_id": user_id})

    try:
        # Fetch user to verify they exist
        kc_user = await keycloak_admin_service.get_user(user_id)
        if not kc_user:
            raise HTTPException(status_code=status.HTTP_404_NOT_FOUND, detail=f"User {user_id} not found")

        # Require a temporary password (email reset is not supported)
        if not request.temporary_password:
            raise HTTPException(status_code=status.HTTP_400_BAD_REQUEST, detail="temporary_password is required")

        # Set the new password via Keycloak Admin API
        logger.info(
            "Setting temporary password for user", extra={"user_id": user_id, "username": kc_user.get("username")}
        )

        success = await keycloak_admin_service.set_user_password(
            user_id=user_id,
            password=request.temporary_password,
            temporary=True,  # User must change on next login
        )

        if not success:
            raise HTTPException(
                status_code=status.HTTP_500_INTERNAL_SERVER_ERROR, detail="Failed to set user password in Keycloak"
            )

        logger.info("Password reset successfully", extra={"user_id": user_id, "username": kc_user.get("username")})

        return {
            "success": True,
            "method": "temporary_password",
            "message": "Temporary password set. User must change password on next login.",
            "temporary_password": request.temporary_password,
        }

    except HTTPException:
        raise
    except ValueError as e:
        logger.error("Invalid password provided", exc_info=e, extra={"user_id": user_id})
        raise HTTPException(status_code=status.HTTP_400_BAD_REQUEST, detail=str(e))
    except Exception as e:
        logger.error("Failed to reset user password", exc_info=e, extra={"user_id": user_id})
        raise HTTPException(
            status_code=status.HTTP_500_INTERNAL_SERVER_ERROR, detail=f"Failed to reset user password: {str(e)}"
        )


@router.post("/import", response_model=BulkUserImportResponse)
async def bulk_import_users(
    request: BulkUserImportRequest,
    user: OIDCUser = Depends(idp.get_current_user(required_roles=["admin.organizations"])),
):
    """Bulk import users from CSV/Excel data.

    This endpoint allows administrators to import multiple users at once.
    Users are created in Keycloak with temporary passwords (must change on first login),
    assigned to the specified organization, and granted organization.read permission.

    Requires admin.organizations role for access.

    Features:
    - Supports up to 100 users per import
    - Duplicate detection (email and username) against existing users
    - Duplicate detection within the import file itself
    - Partial success supported (some rows can fail while others succeed)
    - Optional password generation for users without passwords in CSV

    Args:
        request: Bulk import request containing:
            - organization_id: Target Keycloak organization UUID
            - users: List of user rows (max 100)
            - generate_passwords: Whether to generate passwords for users without one

    Returns:
        BulkUserImportResponse with:
            - success_count: Number of successfully imported users
            - error_count: Number of failed imports
            - total_count: Total number of users in request
            - results: Detailed result per row including:
                - success/failure status
                - error message if failed
                - generated password if applicable

    Raises:
        HTTPException 400: If validation fails (empty users, invalid data)
        HTTPException 403: If caller lacks admin.organizations role
        HTTPException 404: If target organization not found
        HTTPException 500: If Keycloak API call fails
    """
    logger.info(
        "Bulk user import request received",
        extra={
            "admin_user": user.preferred_username,
            "organization_id": request.organization_id,
            "user_count": len(request.users),
            "generate_passwords": request.generate_passwords,
        },
    )

    try:
        result = await import_users_bulk(
            request=request,
            admin_username=user.preferred_username,
        )

        logger.info(
            "Bulk user import completed",
            extra={
                "admin_user": user.preferred_username,
                "organization_id": request.organization_id,
                "success_count": result.success_count,
                "error_count": result.error_count,
            },
        )

        return result

    except HTTPException:
        raise
    except Exception as e:
        logger.error(
            "Unexpected error during bulk user import",
            exc_info=e,
            extra={
                "admin_user": user.preferred_username,
                "organization_id": request.organization_id,
            },
        )
        raise HTTPException(
            status_code=status.HTTP_500_INTERNAL_SERVER_ERROR, detail=f"Failed to import users: {str(e)}"
        )
