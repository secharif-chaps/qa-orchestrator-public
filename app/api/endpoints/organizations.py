"""Organization management endpoints.

Organizations are managed in Keycloak, not in the application database.
These endpoints interact with Keycloak Admin API to fetch organization data.
"""

from typing import Any, Dict, Optional
from fastapi import APIRouter, Depends, HTTPException, status, Query
from fastapi_keycloak import OIDCUser
import requests

from app.core.keycloak import idp
from app.core.config import settings
from app.core.logging_config import get_logger
from app.schemas.organization import OrganizationResponse
from app.schemas.pagination import PaginatedResponse, SortOrder, create_pagination_meta
from app.services.keycloak_admin import keycloak_admin_service

router = APIRouter(prefix="/organizations", tags=["organizations"])
logger = get_logger(__name__)


@router.get("", response_model=PaginatedResponse[OrganizationResponse])
async def list_organizations(
    page: int = Query(1, ge=1, description="Page number (starting from 1)"),
    limit: int = Query(20, ge=1, le=100, description="Items per page (max 100)"),
    sort: str = Query('name', description="Field to sort by (name)"),
    order: SortOrder = Query(SortOrder.ASC, description="Sort order"),
    search: Optional[str] = Query(None, description="Search organizations by name"),
    user: OIDCUser = Depends(idp.get_current_user(required_roles=["admin.organizations"])),
):
    """List all organizations from Keycloak.

    Requires admin.organizations role for access.

    Organizations are managed in Keycloak Organizations feature.
    This endpoint fetches organizations via Keycloak Admin API using admin-cli credentials.

    Args:
        page: Page number (1-indexed)
        limit: Items per page (max 100)
        sort: Field to sort by (currently only 'name' supported)
        order: Sort order (ASC or DESC)
        search: Optional search filter for organization name

    Returns:
        Paginated list of organizations with metadata
    """
    try:
        # Get admin access token using admin-cli client
        token_url = f"{settings.KEYCLOAK_SERVER_URL}/realms/{settings.KEYCLOAK_REALM}/protocol/openid-connect/token"
        token_response = requests.post(
            token_url,
            data={
                "client_id": "admin-cli",
                "client_secret": settings.KEYCLOAK_ADMIN_CLIENT_SECRET,
                "grant_type": "client_credentials"
            },
            headers={"Content-Type": "application/x-www-form-urlencoded"},
            timeout=30
        )
        token_response.raise_for_status()
        admin_token = token_response.json()["access_token"]

        # Fetch organizations from Keycloak
        orgs_url = f"{settings.KEYCLOAK_SERVER_URL}/admin/realms/{settings.KEYCLOAK_REALM}/organizations"
        orgs_response = requests.get(
            orgs_url,
            headers={"Authorization": f"Bearer {admin_token}"},
            timeout=30
        )
        orgs_response.raise_for_status()
        organizations = orgs_response.json()

        logger.info(
            "Fetched organizations from Keycloak",
            extra={
                "count": len(organizations),
                "user": user.preferred_username
            }
        )

        # Apply search filter if provided
        if search:
            search_lower = search.lower()
            organizations = [
                org for org in organizations
                if search_lower in org.get('name', '').lower()
            ]

        # Sort organizations
        reverse = (order == SortOrder.DESC)
        if sort == 'name':
            organizations.sort(key=lambda x: x.get('name', '').lower(), reverse=reverse)

        # Calculate pagination
        total = len(organizations)
        offset = (page - 1) * limit
        paginated_orgs = organizations[offset:offset + limit]

        # Convert to response format using OrganizationResponse schema
        orgs_list = []
        for org in paginated_orgs:
            org_dict = {
                'id': org.get('id'),  # Organization UUID string
                'name': org.get('name', 'Unknown'),
                'description': org.get('description'),
                'slug': org.get('alias', org.get('name', '').lower().replace(' ', '-')),
                'created_at': None,  # Not available from Keycloak API
                'updated_at': None,  # Not available from Keycloak API
                'member_count': 0  # Would require separate API call per organization
            }
            orgs_list.append(OrganizationResponse(**org_dict))

        # Create pagination metadata
        meta = create_pagination_meta(total=total, page=page, per_page=limit)

        return PaginatedResponse(data=orgs_list, meta=meta)

    except requests.exceptions.RequestException as e:
        logger.error(
            "Failed to fetch organizations from Keycloak",
            exc_info=e,
            extra={"user": user.preferred_username}
        )
        raise HTTPException(
            status_code=status.HTTP_503_SERVICE_UNAVAILABLE,
            detail=f"Failed to fetch organizations from Keycloak: {str(e)}"
        )


@router.get("/{organization_id}", response_model=OrganizationResponse)
async def get_organization(
    organization_id: str,
    user: OIDCUser = Depends(idp.get_current_user(required_roles=["admin.organizations"])),
):
    """Get a specific organization by ID from Keycloak.

    Requires admin.organizations role for access.

    Args:
        organization_id: Keycloak organization UUID

    Returns:
        Organization details
    """
    try:
        # Get admin access token
        token_url = f"{settings.KEYCLOAK_SERVER_URL}/realms/{settings.KEYCLOAK_REALM}/protocol/openid-connect/token"
        token_response = requests.post(
            token_url,
            data={
                "client_id": "admin-cli",
                "client_secret": settings.KEYCLOAK_ADMIN_CLIENT_SECRET,
                "grant_type": "client_credentials"
            },
            headers={"Content-Type": "application/x-www-form-urlencoded"},
            timeout=30
        )
        token_response.raise_for_status()
        admin_token = token_response.json()["access_token"]

        # Fetch specific organization
        org_url = f"{settings.KEYCLOAK_SERVER_URL}/admin/realms/{settings.KEYCLOAK_REALM}/organizations/{organization_id}"
        org_response = requests.get(
            org_url,
            headers={"Authorization": f"Bearer {admin_token}"},
            timeout=30
        )

        if org_response.status_code == 404:
            raise HTTPException(
                status_code=status.HTTP_404_NOT_FOUND,
                detail=f"Organization {organization_id} not found"
            )

        org_response.raise_for_status()
        org = org_response.json()

        logger.info(
            "Fetched organization from Keycloak",
            extra={
                "organization_id": organization_id,
                "organization_name": org.get('name'),
                "user": user.preferred_username
            }
        )

        # Convert to response format using OrganizationResponse schema
        org_dict = {
            'id': org.get('id'),  # Organization UUID string
            'name': org.get('name', 'Unknown'),
            'description': org.get('description'),
            'slug': org.get('alias', org.get('name', '').lower().replace(' ', '-')),
            'created_at': None,
            'updated_at': None,
            'member_count': 0
        }

        return OrganizationResponse(**org_dict)

    except requests.exceptions.RequestException as e:
        logger.error(
            "Failed to fetch organization from Keycloak",
            exc_info=e,
            extra={
                "organization_id": organization_id,
                "user": user.preferred_username
            }
        )
        raise HTTPException(
            status_code=status.HTTP_503_SERVICE_UNAVAILABLE,
            detail=f"Failed to fetch organization from Keycloak: {str(e)}"
        )


# Organization Users Management Endpoints
# Note: All organization data is managed in Keycloak via organization UUIDs (strings)


@router.get("/{organization_id}/users")
async def get_organization_users(
    organization_id: str,
    page: int = Query(1, ge=1, description="Page number (1-indexed)"),
    limit: int = Query(20, ge=1, le=100, description="Items per page"),
    search: Optional[str] = None,
    user: OIDCUser = Depends(idp.get_current_user(required_roles=["admin.organizations"]))
):
    """Get paginated list of organization users from Keycloak (admin only).

    Requires admin.organizations role for access.

    Organizations and their members are managed in Keycloak, not in the application database.
    This endpoint fetches members via Keycloak Admin API.

    Args:
        organization_id: Keycloak organization UUID (string)
        page: Page number (1-indexed)
        limit: Items per page (max 100)
        search: Optional search filter for user name/email/username

    Returns:
        Paginated list of organization members from Keycloak
    """
    logger.info(
        "Fetching organization users from Keycloak",
        extra={
            "organization_id": organization_id,
            "page": page,
            "limit": limit,
            "search": search,
            "admin_user": user.preferred_username
        }
    )

    try:
        # Calculate offset (page is 1-indexed in API but 0-indexed in Keycloak)
        first = (page - 1) * limit

        # Fetch members from Keycloak
        members = await keycloak_admin_service.get_organization_members(
            organization_id=organization_id,
            first=first,
            max_results=limit,
            search=search
        )

        # Get total count for pagination
        total = await keycloak_admin_service.count_organization_members(organization_id)

        # Format response
        users_list = []
        for member in members:
            user_dict = {
                'id': member.get('id'),
                'username': member.get('username'),
                'email': member.get('email'),
                'firstName': member.get('firstName'),
                'lastName': member.get('lastName'),
                'enabled': member.get('enabled', True),
                'emailVerified': member.get('emailVerified', False),
                'createdTimestamp': member.get('createdTimestamp')
            }
            users_list.append(user_dict)

        # Create pagination metadata
        meta = create_pagination_meta(total=total, page=page, per_page=limit)

        return {
            'data': users_list,
            'meta': meta
        }

    except HTTPException:
        raise
    except Exception as e:
        logger.error(
            "Failed to fetch organization users",
            exc_info=e,
            extra={
                "organization_id": organization_id,
                "admin_user": user.preferred_username
            }
        )
        raise HTTPException(
            status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
            detail=f"Failed to fetch organization users: {str(e)}"
        )



@router.get("/{organization_id}/users/{user_id}")
async def get_organization_user(
    organization_id: str,
    user_id: str,
    user: OIDCUser = Depends(idp.get_current_user(required_roles=["admin.organizations"]))
):
    """Get a specific organization user from Keycloak (admin only).

    Requires admin.organizations role for access.

    Args:
        organization_id: Keycloak organization UUID
        user_id: Keycloak user UUID

    Returns:
        User details from Keycloak

    Raises:
        HTTPException 404: If user not found
        HTTPException 500: If Keycloak API call fails
    """
    logger.info(
        "Fetching organization user",
        extra={
            "organization_id": organization_id,
            "user_id": user_id,
            "admin_user": user.preferred_username
        }
    )

    try:
        user_data = await keycloak_admin_service.get_user(user_id)

        if not user_data:
            raise HTTPException(
                status_code=status.HTTP_404_NOT_FOUND,
                detail=f"User {user_id} not found"
            )

        return {
            'id': user_data.get('id'),
            'username': user_data.get('username'),
            'email': user_data.get('email'),
            'firstName': user_data.get('firstName'),
            'lastName': user_data.get('lastName'),
            'enabled': user_data.get('enabled', True),
            'emailVerified': user_data.get('emailVerified', False),
            'createdTimestamp': user_data.get('createdTimestamp')
        }

    except HTTPException:
        raise
    except Exception as e:
        logger.error(
            "Failed to fetch user",
            exc_info=e,
            extra={"user_id": user_id, "admin_user": user.preferred_username}
        )
        raise HTTPException(
            status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
            detail=f"Failed to fetch user: {str(e)}"
        )


@router.post("/{organization_id}/users")
async def create_organization_user(
    organization_id: str,
    user_data: Dict[str, Any],
    user: OIDCUser = Depends(idp.get_current_user(required_roles=["admin.organizations"]))
):
    """Create a new user in Keycloak and add to organization (admin only).

    Requires admin.organizations role for access.

    Args:
        organization_id: Keycloak organization UUID
        user_data: User creation data (username, email, firstName, lastName, password, etc.)

    Returns:
        Created user details

    Raises:
        HTTPException 400: If user creation fails
        HTTPException 500: If Keycloak API call fails
    """
    logger.info(
        "=== Starting organization user creation ===",
        extra={
            "organization_id": organization_id,
            "username": user_data.get('username'),
            "email": user_data.get('email'),
            "firstName": user_data.get('firstName'),
            "lastName": user_data.get('lastName'),
            "admin_user": user.preferred_username,
            "admin_user_id": user.sub
        }
    )

    try:
        # Step 1: Create user in Keycloak
        logger.info(
            "STEP 1: Calling keycloak_admin_service.create_user()",
            extra={
                "username": user_data.get('username'),
                "email": user_data.get('email')
            }
        )
        created_user = await keycloak_admin_service.create_user(user_data)

        logger.info(
            "STEP 1 RESULT: User creation response received",
            extra={
                "created_user_keys": list(created_user.keys()) if created_user else None,
                "has_id": 'id' in created_user if created_user else False,
                "user_id": created_user.get('id') if created_user else None
            }
        )

        if not created_user or 'id' not in created_user:
            logger.error(
                "User creation failed - no user ID in response",
                extra={
                    "created_user": created_user,
                    "username": user_data.get('username')
                }
            )
            raise HTTPException(
                status_code=status.HTTP_400_BAD_REQUEST,
                detail="Failed to create user in Keycloak"
            )

        user_id = created_user['id']
        logger.info(
            "User created successfully in Keycloak",
            extra={
                "user_id": user_id,
                "username": created_user.get('username'),
                "email": created_user.get('email')
            }
        )

        # Step 2: Add user to organization
        logger.info(
            "STEP 2: Calling keycloak_admin_service.add_user_to_organization()",
            extra={
                "organization_id": organization_id,
                "user_id": user_id
            }
        )
        add_success = await keycloak_admin_service.add_user_to_organization(
            organization_id=organization_id,
            user_id=user_id
        )

        logger.info(
            "STEP 2 RESULT: Add user to organization response",
            extra={
                "add_success": add_success,
                "organization_id": organization_id,
                "user_id": user_id
            }
        )

        if not add_success:
            logger.warning(
                "User created but failed to add to organization",
                extra={
                    "user_id": user_id,
                    "organization_id": organization_id,
                    "username": created_user.get('username')
                }
            )

        logger.info(
            "=== Successfully completed organization user creation ===",
            extra={
                "user_id": user_id,
                "username": created_user.get('username'),
                "organization_id": organization_id,
                "added_to_org": add_success
            }
        )

        return {
            'id': created_user.get('id'),
            'username': created_user.get('username'),
            'email': created_user.get('email'),
            'firstName': created_user.get('firstName'),
            'lastName': created_user.get('lastName'),
            'enabled': created_user.get('enabled', True),
            'emailVerified': created_user.get('emailVerified', False),
            'createdTimestamp': created_user.get('createdTimestamp')
        }

    except HTTPException as he:
        logger.error(
            "HTTPException during organization user creation",
            extra={
                "status_code": he.status_code,
                "detail": he.detail,
                "organization_id": organization_id,
                "username": user_data.get('username'),
                "admin_user": user.preferred_username
            }
        )
        raise
    except Exception as e:
        logger.error(
            "Unexpected exception during organization user creation",
            exc_info=True,
            extra={
                "error_type": type(e).__name__,
                "error_message": str(e),
                "organization_id": organization_id,
                "username": user_data.get('username'),
                "admin_user": user.preferred_username
            }
        )
        raise HTTPException(
            status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
            detail=f"Failed to create user: {str(e)}"
        )


@router.put("/{organization_id}/users/{user_id}")
async def update_organization_user(
    organization_id: str,
    user_id: str,
    user_data: Dict[str, Any],
    user: OIDCUser = Depends(idp.get_current_user(required_roles=["admin.organizations"]))
):
    """Update user details in Keycloak (admin only).

    Requires admin.organizations role for access.

    Args:
        organization_id: Keycloak organization UUID
        user_id: Keycloak user UUID
        user_data: User update data (email, firstName, lastName, etc.)

    Returns:
        Success status

    Raises:
        HTTPException 500: If Keycloak API call fails
    """
    logger.info(
        "Updating organization user",
        extra={
            "organization_id": organization_id,
            "user_id": user_id,
            "admin_user": user.preferred_username
        }
    )

    try:
        success = await keycloak_admin_service.update_user(user_id, user_data)

        if success:
            logger.info(
                "Successfully updated user",
                extra={"user_id": user_id, "organization_id": organization_id}
            )
            return {"success": True, "message": "User updated successfully"}
        else:
            raise HTTPException(
                status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
                detail="Failed to update user in Keycloak"
            )

    except HTTPException:
        raise
    except Exception as e:
        logger.error(
            "Failed to update user",
            exc_info=e,
            extra={"user_id": user_id, "admin_user": user.preferred_username}
        )
        raise HTTPException(
            status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
            detail=f"Failed to update user: {str(e)}"
        )


@router.delete("/{organization_id}/users/{user_id}")
async def delete_organization_user(
    organization_id: str,
    user_id: str,
    user: OIDCUser = Depends(idp.get_current_user(required_roles=["admin.organizations"]))
):
    """Remove user from organization (admin only).

    This removes the user from the organization but does NOT delete the user from Keycloak.
    The user can still be added to other organizations.

    Requires admin.organizations role for access.

    Args:
        organization_id: Keycloak organization UUID
        user_id: Keycloak user UUID

    Returns:
        Success status

    Raises:
        HTTPException 500: If Keycloak API call fails
    """
    logger.info(
        "Removing user from organization",
        extra={
            "organization_id": organization_id,
            "user_id": user_id,
            "admin_user": user.preferred_username
        }
    )

    try:
        success = await keycloak_admin_service.remove_user_from_organization(
            organization_id=organization_id,
            user_id=user_id
        )

        if success:
            logger.info(
                "Successfully removed user from organization",
                extra={"user_id": user_id, "organization_id": organization_id}
            )
            return {"success": True, "message": "User removed from organization"}
        else:
            raise HTTPException(
                status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
                detail="Failed to remove user from organization"
            )

    except HTTPException:
        raise
    except Exception as e:
        logger.error(
            "Failed to remove user from organization",
            exc_info=e,
            extra={"user_id": user_id, "admin_user": user.preferred_username}
        )
        raise HTTPException(
            status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
            detail=f"Failed to remove user: {str(e)}"
        )


@router.post("/{organization_id}/users/{user_id}/reset-password")
async def reset_user_password(
    organization_id: str,
    user_id: str,
    user: OIDCUser = Depends(idp.get_current_user(required_roles=["admin.organizations"]))
):
    """Send password reset email to user via Keycloak (admin only).

    Requires admin.organizations role for access.

    Args:
        organization_id: Keycloak organization UUID
        user_id: Keycloak user UUID

    Returns:
        Success status

    Raises:
        HTTPException 500: If Keycloak API call fails
    """
    logger.info(
        "Sending password reset email",
        extra={
            "organization_id": organization_id,
            "user_id": user_id,
            "admin_user": user.preferred_username
        }
    )

    try:
        success = await keycloak_admin_service.send_password_reset_email(user_id)

        if success:
            logger.info(
                "Successfully sent password reset email",
                extra={"user_id": user_id}
            )
            return {"success": True, "message": "Password reset email sent"}
        else:
            raise HTTPException(
                status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
                detail="Failed to send password reset email"
            )

    except HTTPException:
        raise
    except Exception as e:
        logger.error(
            "Failed to send password reset email",
            exc_info=e,
            extra={"user_id": user_id, "admin_user": user.preferred_username}
        )
        raise HTTPException(
            status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
            detail=f"Failed to send password reset email: {str(e)}"
        )


@router.patch("/{organization_id}/users/{user_id}/status")
async def update_user_status(
    organization_id: str,
    user_id: str,
    status_data: Dict[str, bool],
    user: OIDCUser = Depends(idp.get_current_user(required_roles=["admin.organizations"]))
):
    """Enable or disable user in Keycloak (admin only).

    Requires admin.organizations role for access.

    Args:
        organization_id: Keycloak organization UUID
        user_id: Keycloak user UUID
        status_data: Dict with 'enabled' key (true/false)

    Returns:
        Success status

    Raises:
        HTTPException 400: If 'enabled' key not provided
        HTTPException 500: If Keycloak API call fails
    """
    logger.info(
        "Updating user status",
        extra={
            "organization_id": organization_id,
            "user_id": user_id,
            "enabled": status_data.get('enabled'),
            "admin_user": user.preferred_username
        }
    )

    if 'enabled' not in status_data:
        raise HTTPException(
            status_code=status.HTTP_400_BAD_REQUEST,
            detail="'enabled' field is required in request body"
        )

    try:
        success = await keycloak_admin_service.update_user(
            user_id,
            {"enabled": status_data['enabled']}
        )

        if success:
            status_text = "enabled" if status_data['enabled'] else "disabled"
            logger.info(
                f"Successfully {status_text} user",
                extra={"user_id": user_id}
            )
            return {"success": True, "message": f"User {status_text}"}
        else:
            raise HTTPException(
                status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
                detail="Failed to update user status"
            )

    except HTTPException:
        raise
    except Exception as e:
        logger.error(
            "Failed to update user status",
            exc_info=e,
            extra={"user_id": user_id, "admin_user": user.preferred_username}
        )
        raise HTTPException(
            status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
            detail=f"Failed to update user status: {str(e)}"
        )
