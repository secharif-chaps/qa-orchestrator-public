"""Team Management Endpoints.

Provides endpoints for managing users within organizations via Keycloak Admin API.
Route: /api/organizations/{organizationId}/users

All endpoints require organization.write role for access.
"""

from typing import Optional
from fastapi import APIRouter, Depends, HTTPException, status, Query, Path
from fastapi_keycloak import OIDCUser
from sqlalchemy.orm import Session

from app.database import get_db
from app.core.keycloak import idp
from app.core.logging_config import get_logger
from app.schemas.team_management import (
    OrganizationUser,
    OrganizationUserCreate,
    OrganizationUserUpdate,
    TeamUserStatus,
    TeamUserSortField
)
from app.schemas.pagination import PaginatedResponse, create_pagination_meta
from app.services.keycloak_admin import keycloak_admin_service

router = APIRouter(prefix="/organizations", tags=["team-management"])
logger = get_logger(__name__)


@router.get("/{organization_id}/users", response_model=PaginatedResponse[OrganizationUser])
async def list_organization_users(
    organization_id: str = Path(..., description="Organization UUID"),
    page: int = Query(1, ge=1, description="Page number (starting from 1)"),
    limit: int = Query(20, ge=1, le=100, description="Items per page"),
    search: Optional[str] = Query(None, max_length=100, description="Search term for name, email, username"),
    sort: TeamUserSortField = Query(TeamUserSortField.CREATED_AT, description="Field to sort by"),
    order: str = Query("desc", pattern="^(asc|desc)$", description="Sort order"),
    status: TeamUserStatus = Query(TeamUserStatus.ACTIVE, description="Filter by user status"),
    user: OIDCUser = Depends(idp.get_current_user(required_roles=["organization.write"])),
    db: Session = Depends(get_db)
):
    """List organization users with pagination, search, and filtering.

    Requires organization.write role for access.

    Fetches users from Keycloak organization membership and their roles.
    """

    try:
        # Calculate offset for pagination
        offset = (page - 1) * limit

        # Get organization members from Keycloak
        keycloak_members = await keycloak_admin_service.get_organization_members(
            organization_id=organization_id,
            first=offset,
            max_results=limit,
            search=search
        )

        if not keycloak_members:
            logger.warning(f"No members found for organization {organization_id}")
            return PaginatedResponse(
                data=[],
                meta=create_pagination_meta(total=0, page=page, per_page=limit)
            )

        # Build user list with permissions from Keycloak
        users = []
        for member in keycloak_members:
            user_id = member.get('id')
            username = member.get('username', '')
            email = member.get('email', '')
            first_name = member.get('firstName', '')
            last_name = member.get('lastName', '')
            is_enabled = member.get('enabled', True)
            created_timestamp = member.get('createdTimestamp', 0)

            # Apply status filter
            if status == TeamUserStatus.ACTIVE and not is_enabled:
                continue
            elif status == TeamUserStatus.DISABLED and is_enabled:
                continue

            # Get user's realm roles (permissions) from Keycloak
            user_roles_response = await keycloak_admin_service.get_user_realm_roles(user_id)
            user_permissions = [role['name'] for role in user_roles_response] if user_roles_response else []

            # Fallback to default permission if none found
            if not user_permissions:
                user_permissions = ["organization.read"]

            organization_user = OrganizationUser(
                id=hash(user_id) % (10 ** 8),  # Generate integer ID from UUID hash for compatibility
                email=email,
                username=username,
                first_name=first_name,
                last_name=last_name,
                created_at=str(created_timestamp) if created_timestamp else "",
                updated_at=str(created_timestamp) if created_timestamp else "",
                is_disabled=not is_enabled,
                permissions=user_permissions,
                created_by=None  # Not tracked in Keycloak
            )
            users.append(organization_user)

        # TODO: Implement proper sorting and total count from Keycloak
        # For now, return basic pagination
        total = len(users)

        # Create pagination metadata
        meta = create_pagination_meta(total=total, page=page, per_page=limit)

        return PaginatedResponse(data=users, meta=meta)

    except Exception as e:
        logger.error(f"Error listing organization users: {e}", exc_info=True)
        raise HTTPException(
            status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
            detail=f"Failed to list organization users: {str(e)}"
        )


@router.post("/{organization_id}/users", response_model=OrganizationUser)
async def create_organization_user(
    organization_id: str = Path(..., description="Organization UUID"),
    user_data: OrganizationUserCreate = ...,
    user: OIDCUser = Depends(idp.get_current_user(required_roles=["organization.write"])),
    db: Session = Depends(get_db)
):
    """Create a new user in the organization.

    Requires organization.write role for access.

    Creates user in Keycloak, assigns to organization, and sets permissions via realm roles.
    """

    try:
        # Create user in Keycloak
        keycloak_user = await keycloak_admin_service.create_user({
            "username": user_data.username,
            "email": user_data.email,
            "firstName": user_data.first_name or "",
            "lastName": user_data.last_name or "",
            "enabled": True,
            "emailVerified": True,
            "credentials": [{
                "type": "password",
                "value": user_data.password,
                "temporary": False
            }]
        })

        if not keycloak_user or 'id' not in keycloak_user:
            raise HTTPException(
                status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
                detail="Failed to create user in Keycloak"
            )

        user_id = keycloak_user['id']

        # Add user to organization
        await keycloak_admin_service.add_user_to_organization(user_id, organization_id)

        # Assign permissions (realm roles) if provided
        if user_data.permissions:
            await keycloak_admin_service.sync_user_realm_roles(
                user_id=user_id,
                target_roles=user_data.permissions
            )

        # Fetch the created user's permissions
        user_roles_response = await keycloak_admin_service.get_user_realm_roles(user_id)
        user_permissions = [role['name'] for role in user_roles_response] if user_roles_response else []

        if not user_permissions:
            user_permissions = ["organization.read"]

        # Return created user
        return OrganizationUser(
            id=hash(user_id) % (10 ** 8),
            email=user_data.email,
            username=user_data.username,
            first_name=user_data.first_name or "",
            last_name=user_data.last_name or "",
            created_at="",  # Keycloak will set this
            updated_at="",
            is_disabled=False,
            permissions=user_permissions,
            created_by=None
        )

    except HTTPException:
        raise
    except Exception as e:
        logger.error(f"Error creating organization user: {e}", exc_info=True)
        raise HTTPException(
            status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
            detail=f"Failed to create user: {str(e)}"
        )


@router.patch("/{organization_id}/users/{user_id}", response_model=OrganizationUser)
async def update_organization_user(
    organization_id: str = Path(..., description="Organization UUID"),
    user_id: str = Path(..., description="Keycloak User UUID"),
    user_update: OrganizationUserUpdate = ...,
    user: OIDCUser = Depends(idp.get_current_user(required_roles=["organization.write"])),
    db: Session = Depends(get_db)
):
    """Update organization user details and permissions.

    Requires organization.write role for access.

    Updates user information in Keycloak and syncs realm roles (permissions).
    """

    try:
        # Get current user from Keycloak
        keycloak_user = await keycloak_admin_service.get_user(user_id)
        if not keycloak_user:
            raise HTTPException(
                status_code=status.HTTP_404_NOT_FOUND,
                detail="User not found"
            )

        # Prepare update data
        update_data = {}

        if user_update.is_disabled is not None:
            update_data['enabled'] = not user_update.is_disabled

        if user_update.first_name is not None:
            update_data['firstName'] = user_update.first_name

        if user_update.last_name is not None:
            update_data['lastName'] = user_update.last_name

        # Update user in Keycloak if there are changes
        if update_data:
            success = await keycloak_admin_service.update_user(user_id, update_data)
            if not success:
                raise HTTPException(
                    status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
                    detail="Failed to update user in Keycloak"
                )

        # Update permissions (realm roles) if provided
        if user_update.permissions is not None:
            await keycloak_admin_service.sync_user_realm_roles(
                user_id=user_id,
                target_roles=user_update.permissions
            )

        # Fetch updated user data
        updated_user = await keycloak_admin_service.get_user(user_id)
        user_roles_response = await keycloak_admin_service.get_user_realm_roles(user_id)
        user_permissions = [role['name'] for role in user_roles_response] if user_roles_response else []

        if not user_permissions:
            user_permissions = ["organization.read"]

        return OrganizationUser(
            id=hash(user_id) % (10 ** 8),
            email=updated_user.get('email', ''),
            username=updated_user.get('username', ''),
            first_name=updated_user.get('firstName', ''),
            last_name=updated_user.get('lastName', ''),
            created_at=str(updated_user.get('createdTimestamp', '')),
            updated_at=str(updated_user.get('createdTimestamp', '')),
            is_disabled=not updated_user.get('enabled', True),
            permissions=user_permissions,
            created_by=None
        )

    except HTTPException:
        raise
    except Exception as e:
        logger.error(f"Error updating organization user: {e}", exc_info=True)
        raise HTTPException(
            status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
            detail=f"Failed to update user: {str(e)}"
        )


@router.get("/{organization_id}/users/{user_id}", response_model=OrganizationUser)
async def get_organization_user(
    organization_id: str = Path(..., description="Organization UUID"),
    user_id: str = Path(..., description="Keycloak User UUID"),
    current_user: OIDCUser = Depends(idp.get_current_user(required_roles=["organization.write"])),
    db: Session = Depends(get_db)
):
    """Get detailed information about a specific organization user.

    Requires organization.write role for access.

    Fetches user details and permissions from Keycloak.
    """

    try:
        # Get user from Keycloak
        keycloak_user = await keycloak_admin_service.get_user(user_id)
        if not keycloak_user:
            raise HTTPException(
                status_code=status.HTTP_404_NOT_FOUND,
                detail="User not found in organization"
            )

        # Get user's realm roles (permissions)
        user_roles_response = await keycloak_admin_service.get_user_realm_roles(user_id)
        user_permissions = [role['name'] for role in user_roles_response] if user_roles_response else []

        if not user_permissions:
            user_permissions = ["organization.read"]

        # Return user details
        return OrganizationUser(
            id=hash(user_id) % (10 ** 8),
            email=keycloak_user.get('email', ''),
            username=keycloak_user.get('username', ''),
            first_name=keycloak_user.get('firstName', ''),
            last_name=keycloak_user.get('lastName', ''),
            created_at=str(keycloak_user.get('createdTimestamp', '')),
            updated_at=str(keycloak_user.get('createdTimestamp', '')),
            is_disabled=not keycloak_user.get('enabled', True),
            permissions=user_permissions,
            created_by=None
        )

    except HTTPException:
        raise
    except Exception as e:
        logger.error(f"Error getting organization user: {e}", exc_info=True)
        raise HTTPException(
            status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
            detail=f"Failed to get user: {str(e)}"
        )
