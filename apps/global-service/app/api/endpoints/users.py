"""User management API endpoints.

Provides endpoints for managing users across all organizations.
Requires admin.organizations role for access.
"""

import asyncio
import time
from typing import Any

from fastapi import APIRouter, Depends, HTTPException, Query, status

from app.core.config import settings
from app.core.keycloak import OIDCUser, idp
from app.core.logging_config import get_logger
from app.core.permissions import get_tier_from_roles
from app.schemas.user import (
    AssignOrganizationRequest,
    BulkUserImportRequest,
    BulkUserImportResponse,
    ResetPasswordRequest,
    UpdatePermissionsRequest,
)
from app.services.keycloak_admin import keycloak_admin_service
from app.services.user_import import import_users_bulk

router = APIRouter(prefix="/users", tags=["users"])
logger = get_logger(__name__)

# Performance limits for organization-based user search
MAX_ORGS_TO_SEARCH = 10
MAX_MEMBERS_PER_ORG = 100
MAX_CONCURRENT_ORG_REQUESTS = 5
MAX_TOTAL_USERS_FROM_ORGS = 500


def transform_keycloak_user(
    kc_user: dict[str, Any],
    organization: dict[str, Any] | None = None,
) -> dict[str, Any]:
    """Transform a Keycloak user dict into the API response format.

    Args:
        kc_user: Keycloak user dictionary
        organization: Optional organization dictionary with 'id' and 'name'
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


def sort_users(users: list[dict[str, Any]], sort: str, order: str) -> None:
    """Sort users list in place by the given field and order."""
    reverse = order.lower() == "desc"
    if sort == "username":
        users.sort(key=lambda u: (u["username"] or "").lower(), reverse=reverse)
    elif sort == "created_at":
        users.sort(key=lambda u: int(u["created_at"]), reverse=reverse)


async def search_users_with_org(search: str) -> list[dict[str, Any]]:
    """Search users by name/email/username AND by organization name.

    Runs Keycloak user search and organization search in parallel,
    then merges and deduplicates the results.
    """
    kc_users, matching_orgs = await asyncio.gather(
        keycloak_admin_service.search_users(search=search, first=0, max_results=500),
        keycloak_admin_service.search_organizations(search),
    )

    seen_user_ids = {u.get("id") for u in kc_users}

    if matching_orgs:
        orgs_to_search = [org for org in matching_orgs if org.get("id")][
            :MAX_ORGS_TO_SEARCH
        ]

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
            semaphore = asyncio.Semaphore(MAX_CONCURRENT_ORG_REQUESTS)
            users_from_orgs = 0

            async def fetch_org_members(
                org: dict[str, Any],
            ) -> list[dict[str, Any]]:
                async with semaphore:
                    return await keycloak_admin_service.get_organization_members(
                        org.get("id"), first=0, max_results=MAX_MEMBERS_PER_ORG
                    )

            org_member_results = await asyncio.gather(
                *[fetch_org_members(org) for org in orgs_to_search]
            )

            for members in org_member_results:
                for member in members:
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
                break

    return kc_users


async def list_users_paginated(
    first: int,
    limit: int,
) -> tuple[list[dict[str, Any]], int]:
    """List users without search using Keycloak's native pagination."""
    total = await keycloak_admin_service.count_users_with_search(None)
    kc_users = await keycloak_admin_service.search_users(
        search=None, first=first, max_results=limit
    )
    return kc_users, total


async def update_user_enabled_status(
    user_id: str,
    enabled: bool,
    admin_username: str,
) -> dict[str, Any]:
    """Update user enabled/disabled status in Keycloak."""
    action = "Enabling" if enabled else "Disabling"
    action_past = "enabled" if enabled else "disabled"
    status_value = "active" if enabled else "revoked"

    logger.info(
        f"{action} user account",
        extra={"admin_user": admin_username, "user_id": user_id},
    )

    try:
        success = await keycloak_admin_service.update_user(
            user_id=user_id, user_data={"enabled": enabled}
        )

        if not success:
            raise HTTPException(
                status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
                detail={
                    "error": "user_action_failed",
                    "message": f"Failed to {action.lower()} user",
                    "user_id": user_id,
                },
            )

        kc_user = await keycloak_admin_service.get_user(user_id)
        if not kc_user:
            raise HTTPException(
                status_code=status.HTTP_404_NOT_FOUND,
                detail={
                    "error": "user_not_found",
                    "message": "User not found",
                    "user_id": user_id,
                },
            )

        user_roles = await keycloak_admin_service.get_user_realm_roles(user_id)

        internal_roles = {
            "uma_authorization",
            "offline_access",
            "default-roles-" + settings.KEYCLOAK_REALM.lower(),
        }
        permissions = [
            role["name"]
            for role in user_roles
            if role["name"] not in internal_roles
            and not role["name"].startswith("realm-management")
        ]

        attributes = kc_user.get("attributes", {})
        user_data = {
            "user_id": user_id,
            "username": kc_user.get("username"),
            "email": kc_user.get("email"),
            "organization_id": (
                attributes.get("organization_id", [None])[0]
                if "organization_id" in attributes
                else None
            ),
            "organization_name": (
                attributes.get("organization_name", [None])[0]
                if "organization_name" in attributes
                else None
            ),
            "status": status_value,
            "created_at": str(kc_user.get("createdTimestamp", 0)),
            "permissions": permissions,
        }

        logger.info(
            f"Successfully {action_past} user", extra={"user_id": user_id}
        )

        return user_data

    except HTTPException:
        raise
    except Exception as e:
        logger.error(
            f"Failed to {action.lower()} user",
            exc_info=e,
            extra={"user_id": user_id},
        )
        raise HTTPException(
            status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
            detail={
                "error": "user_action_failed",
                "message": f"Failed to {action.lower()} user",
                "user_id": user_id,
            },
        )


# ── Endpoints ────────────────────────────────────────────────────


@router.get("")
async def get_all_users(
    page: int = Query(1, ge=1, description="Page number (starting from 1)"),
    limit: int = Query(20, ge=1, le=100, description="Items per page (max 100)"),
    search: str | None = Query(
        None, description="Search by username, name, email or organization"
    ),
    sort: str = Query("created_at", description="Sort field: username, created_at"),
    order: str = Query("desc", description="Sort order: asc or desc"),
    user: OIDCUser = Depends(
        idp.get_current_user(required_roles=["admin.organizations"])
    ),
):
    """Get all users with search and pagination."""
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
            kc_users = await search_users_with_org(search)
            total = len(kc_users)
        else:
            first = (page - 1) * limit
            kc_users, total = await list_users_paginated(first, limit)

        # Fetch permission tiers for all users in parallel
        async def fetch_user_role(user_id: str) -> tuple[str, str | None]:
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
                    if role["name"] not in internal_roles
                    and not role["name"].startswith("realm-management")
                ]
                tier = get_tier_from_roles(permissions)
                return user_id, tier.value
            except Exception as e:
                logger.warning(
                    "Failed to fetch permissions for user",
                    extra={"user_id": user_id, "error": str(e)},
                )
                return user_id, None

        # Fetch organization for a single user
        async def fetch_user_organization(user_id: str) -> tuple[str, dict[str, Any] | None]:
            try:
                org = await keycloak_admin_service.get_user_organization_optimized(user_id)
                return user_id, org
            except Exception as e:
                logger.warning(
                    "Failed to fetch organization for user",
                    extra={"user_id": user_id, "error": str(e)},
                )
                return user_id, None

        # Fetch roles and organizations in parallel
        role_tasks = [fetch_user_role(kc_user.get("id")) for kc_user in kc_users]
        org_tasks = [fetch_user_organization(kc_user.get("id")) for kc_user in kc_users]
        role_results, org_results = await asyncio.gather(
            asyncio.gather(*role_tasks),
            asyncio.gather(*org_tasks),
        )
        user_roles_map = dict(role_results)
        user_orgs_map = dict(org_results)

        users_data = [
            {
                **transform_keycloak_user(u, organization=user_orgs_map.get(u.get("id"))),
                "permission_tier": user_roles_map.get(u.get("id")),
            }
            for u in kc_users
        ]
        sort_users(users_data, sort, order)

        # Apply pagination for search results (already paginated for non-search)
        if search and search.strip():
            first_idx = (page - 1) * limit
            users_data = users_data[first_idx : first_idx + limit]

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
            "pagination": {
                "page": page,
                "limit": limit,
                "total": total,
                "total_pages": total_pages,
            },
        }

    except Exception as e:
        logger.error(
            "Failed to fetch users from Keycloak",
            exc_info=e,
            extra={"page": page, "limit": limit, "search": search},
        )
        raise HTTPException(
            status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
            detail={
                "error": "fetch_users_failed",
                "message": "Failed to fetch users",
            },
        )


@router.put("/{user_id}/organization")
async def assign_user_to_organization(
    user_id: str,
    request: AssignOrganizationRequest,
    user: OIDCUser = Depends(
        idp.get_current_user(required_roles=["admin.organizations"])
    ),
):
    """Assign a user to a different organization."""
    organization_id = request.organization_id

    logger.info(
        "Assigning user to organization",
        extra={
            "admin_user": user.preferred_username,
            "user_id": user_id,
            "organization_id": organization_id,
        },
    )

    if not organization_id:
        raise HTTPException(
            status_code=status.HTTP_400_BAD_REQUEST,
            detail={
                "error": "missing_organization",
                "message": "organization_id parameter is required",
                "user_id": user_id,
            },
        )

    try:
        # Get user's current organizations to remove them
        current_orgs = await keycloak_admin_service.get_user_organizations(user_id)

        logger.info(
            "User's current organizations",
            extra={
                "user_id": user_id,
                "current_orgs": [org.get("id") for org in current_orgs],
                "current_org_names": [org.get("name") for org in current_orgs],
            },
        )

        # Remove user from all current organizations (except the target)
        for org in current_orgs:
            org_id = org.get("id")
            if org_id and org_id != organization_id:
                logger.info(
                    "Removing user from old organization",
                    extra={
                        "user_id": user_id,
                        "organization_id": org_id,
                        "organization_name": org.get("name"),
                    },
                )
                try:
                    await keycloak_admin_service.remove_user_from_organization(
                        organization_id=org_id, user_id=user_id
                    )
                    logger.info(
                        "Successfully removed user from old organization",
                        extra={"user_id": user_id, "organization_id": org_id},
                    )
                except Exception as remove_error:
                    logger.warning(
                        "Failed to remove user from old organization, continuing",
                        extra={
                            "user_id": user_id,
                            "organization_id": org_id,
                            "error": str(remove_error),
                        },
                    )

        # Check if user is already in the target organization
        is_already_member = any(
            org.get("id") == organization_id for org in current_orgs
        )

        if is_already_member:
            logger.info(
                "User is already a member of target organization",
                extra={
                    "user_id": user_id,
                    "organization_id": organization_id,
                },
            )
            return {
                "success": True,
                "message": f"User {user_id} successfully assigned to organization {organization_id}",
                "user_id": user_id,
                "organization_id": organization_id,
            }

        # Add user to the new organization
        success = await keycloak_admin_service.add_user_to_organization(
            organization_id=organization_id, user_id=user_id
        )

        if success:
            logger.info(
                "Successfully assigned user to organization",
                extra={
                    "user_id": user_id,
                    "organization_id": organization_id,
                },
            )
            return {
                "success": True,
                "message": f"User {user_id} successfully assigned to organization {organization_id}",
                "user_id": user_id,
                "organization_id": organization_id,
            }
        else:
            raise HTTPException(
                status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
                detail={
                    "error": "user_action_failed",
                    "message": "Failed to assign user to organization",
                    "user_id": user_id,
                },
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
            status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
            detail={
                "error": "user_action_failed",
                "message": "Failed to assign user to organization",
                "user_id": user_id,
            },
        )


@router.put("/{user_id}/permissions")
async def update_user_permissions(
    user_id: str,
    request: UpdatePermissionsRequest,
    user: OIDCUser = Depends(
        idp.get_current_user(required_roles=["admin.organizations"])
    ),
):
    """Update user's permissions by syncing their Keycloak realm roles."""
    logger.info(
        "Updating user permissions",
        extra={
            "admin_user": user.preferred_username,
            "user_id": user_id,
            "permissions": request.permissions,
        },
    )

    try:
        success = await keycloak_admin_service.sync_user_realm_roles(
            user_id=user_id, target_roles=request.permissions
        )

        if not success:
            raise HTTPException(
                status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
                detail={
                    "error": "user_action_failed",
                    "message": "Failed to update user permissions",
                    "user_id": user_id,
                },
            )

        # Fetch updated user data
        kc_user = await keycloak_admin_service.get_user(user_id)
        if not kc_user:
            raise HTTPException(
                status_code=status.HTTP_404_NOT_FOUND,
                detail={
                    "error": "user_not_found",
                    "message": "User not found",
                    "user_id": user_id,
                },
            )

        # Fetch updated roles
        user_roles = await keycloak_admin_service.get_user_realm_roles(user_id)

        internal_roles = {
            "uma_authorization",
            "offline_access",
            "default-roles-" + settings.KEYCLOAK_REALM.lower(),
        }
        permissions = [
            role["name"]
            for role in user_roles
            if role["name"] not in internal_roles
            and not role["name"].startswith("realm-management")
        ]

        attributes = kc_user.get("attributes", {})
        user_data = {
            "user_id": user_id,
            "username": kc_user.get("username"),
            "email": kc_user.get("email"),
            "organization_id": (
                attributes.get("organization_id", [None])[0]
                if "organization_id" in attributes
                else None
            ),
            "organization_name": (
                attributes.get("organization_name", [None])[0]
                if "organization_name" in attributes
                else None
            ),
            "status": "active" if kc_user.get("enabled", True) else "revoked",
            "created_at": str(kc_user.get("createdTimestamp", 0)),
            "permissions": permissions,
        }

        logger.info(
            "Successfully updated user permissions",
            extra={"user_id": user_id, "permissions": permissions},
        )

        return user_data

    except HTTPException:
        raise
    except ValueError as e:
        logger.error(
            "Invalid permissions provided",
            exc_info=e,
            extra={"user_id": user_id, "permissions": request.permissions},
        )
        raise HTTPException(
            status_code=status.HTTP_400_BAD_REQUEST,
            detail={
                "error": "invalid_permissions",
                "message": str(e),
                "user_id": user_id,
            },
        )
    except Exception as e:
        logger.error(
            "Failed to update user permissions",
            exc_info=e,
            extra={"user_id": user_id},
        )
        raise HTTPException(
            status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
            detail={
                "error": "user_action_failed",
                "message": "Failed to update user permissions",
                "user_id": user_id,
            },
        )


@router.put("/{user_id}/disable")
async def disable_user(
    user_id: str,
    user: OIDCUser = Depends(
        idp.get_current_user(required_roles=["admin.organizations"])
    ),
) -> dict[str, Any]:
    """Disable a user account (soft delete — account exists but cannot login)."""
    return await update_user_enabled_status(
        user_id=user_id,
        enabled=False,
        admin_username=user.preferred_username,
    )


@router.put("/{user_id}/enable")
async def enable_user(
    user_id: str,
    user: OIDCUser = Depends(
        idp.get_current_user(required_roles=["admin.organizations"])
    ),
) -> dict[str, Any]:
    """Enable a previously disabled user account."""
    return await update_user_enabled_status(
        user_id=user_id,
        enabled=True,
        admin_username=user.preferred_username,
    )


@router.post("/{user_id}/reset-password")
async def reset_user_password(
    user_id: str,
    request: ResetPasswordRequest,
    user: OIDCUser = Depends(
        idp.get_current_user(required_roles=["admin.organizations"])
    ),
):
    """Reset user password by setting a temporary password."""
    logger.info(
        "Resetting user password",
        extra={
            "admin_user": user.preferred_username,
            "user_id": user_id,
        },
    )

    try:
        kc_user = await keycloak_admin_service.get_user(user_id)
        if not kc_user:
            raise HTTPException(
                status_code=status.HTTP_404_NOT_FOUND,
                detail={
                    "error": "user_not_found",
                    "message": "User not found",
                    "user_id": user_id,
                },
            )

        if not request.temporary_password:
            raise HTTPException(
                status_code=status.HTTP_400_BAD_REQUEST,
                detail={
                    "error": "missing_password",
                    "message": "temporary_password is required",
                    "user_id": user_id,
                },
            )

        logger.info(
            "Setting temporary password for user",
            extra={"user_id": user_id, "username": kc_user.get("username")},
        )

        success = await keycloak_admin_service.set_user_password(
            user_id=user_id,
            password=request.temporary_password,
            temporary=True,
        )

        if not success:
            raise HTTPException(
                status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
                detail={
                    "error": "user_action_failed",
                    "message": "Failed to reset password",
                    "user_id": user_id,
                },
            )

        logger.info(
            "Password reset successfully",
            extra={"user_id": user_id, "username": kc_user.get("username")},
        )

        return {
            "success": True,
            "method": "temporary_password",
            "message": "Temporary password set. User must change password on next login.",
        }

    except HTTPException:
        raise
    except ValueError as e:
        logger.error(
            "Invalid password provided",
            exc_info=e,
            extra={"user_id": user_id},
        )
        raise HTTPException(
            status_code=status.HTTP_400_BAD_REQUEST,
            detail={
                "error": "invalid_password",
                "message": str(e),
                "user_id": user_id,
            },
        )
    except Exception as e:
        logger.error(
            "Failed to reset user password",
            exc_info=e,
            extra={"user_id": user_id},
        )
        raise HTTPException(
            status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
            detail={
                "error": "user_action_failed",
                "message": "Failed to reset password",
                "user_id": user_id,
            },
        )


@router.post("/import", response_model=BulkUserImportResponse)
async def bulk_import_users(
    request: BulkUserImportRequest,
    user: OIDCUser = Depends(idp.get_current_user(required_roles=["admin.organizations"]))
):
    """Bulk import users into Keycloak.

    Creates users with temporary passwords, assigns them to the specified
    organization, and grants organization.read permission.
    Requires admin.organizations role.
    """
    try:
        return await import_users_bulk(
            request=request,
            admin_username=user.preferred_username,
        )
    except HTTPException:
        raise
    except Exception as e:
        logger.error(
            "Unexpected error during bulk user import",
            exc_info=e,
            extra={
                "admin_user": user.preferred_username,
                "organization_id": request.organization_id,
            }
        )
        raise HTTPException(
            status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
            detail="Internal error during bulk import"
        )
