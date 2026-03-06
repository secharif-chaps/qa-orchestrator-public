"""Folder API endpoints with sharing and access control.

This module provides REST API endpoints for:
- Folder CRUD operations (with owner-only write access)
- Folder sharing management (owner-only)
- Folder items management (role-based access)
- User favorites
"""

from uuid import UUID

from fastapi import APIRouter, Depends, HTTPException, Query, status
from fastapi_keycloak import OIDCUser
from sqlalchemy.ext.asyncio import AsyncSession

from app.core.keycloak import idp
from app.core.logging_config import get_logger
from app.core.organization import OrganizationContext, get_user_organization
from app.database import get_global_db
from app.models.folder import ShareRole
from app.schemas.folder import (
    FolderCreate,
    FolderItemAdd,
    FolderItemMove,
    FolderItemMoveResponse,
    FolderItemResponse,
    FolderResponse,
    FolderShareCreate,
    FolderShareResponse,
    FolderShareUpdate,
    FolderUpdate,
    FolderWithItemsResponse,
    UserSearchResult,
)
from app.services.folder import FolderService
from app.services.keycloak_admin import keycloak_admin_service

router = APIRouter(prefix="/folders", tags=["folders"])
logger = get_logger(__name__)


async def _build_folder_response(
    db: AsyncSession,
    folder,
    user_id: str,
    user_favorite_ids: set = None,
    org_id: str = "",
    username: str = "",
    org_name: str = "",
    user_roles: list[str] = None,
) -> dict:
    """Build folder response dict with access control fields.

    This helper builds a consistent folder response including:
    - owner_id: For frontend access control checks
    - is_owner: Boolean indicating if current user owns folder
    - share_role: User's role ('owner', 'writer', 'reader', or None)
    - is_favorite: User-specific favorite status

    Args:
        db: Database session
        folder: Folder model instance
        user_id: Current user's Keycloak UUID
        user_favorite_ids: Optional set of favorited folder IDs (for list views)
        org_id: Organization UUID (for backend company enrichment)
        username: User's username (for backend company enrichment)
        org_name: Organization name (for backend company enrichment)
        user_roles: User's realm roles (for backend company enrichment)

    Returns:
        Dict ready for FolderResponse serialization
    """
    # Determine user's role for this folder
    share_role = await FolderService.get_user_folder_role(db, folder.id, user_id)
    is_owner = share_role == "owner"

    # Determine favorite status
    if user_favorite_ids is not None:
        is_favorite = folder.id in user_favorite_ids
    else:
        is_favorite = await FolderService.is_favorite(db, folder.id, user_id)

    # Get folder items with full context for company enrichment
    folder_items = await FolderService._get_folder_items_summary(
        db, folder.id,
        user_id=user_id,
        org_id=org_id,
        username=username,
        org_name=org_name,
        roles=user_roles,
    )

    return {
        "id": folder.id,
        "organization_id": folder.organization_id,
        "owner": folder.owner or "Unknown",
        "owner_id": folder.owner_id,
        "owner_username": folder.owner or "Unknown",
        "is_owner": is_owner,
        "share_role": share_role,
        "name": folder.name,
        "color": folder.color,
        "icon": folder.icon,
        "tags": folder.tags,
        "is_favorite": is_favorite,
        "is_deleted": folder.is_deleted,
        "created_at": folder.created_at,
        "updated_at": folder.updated_at,
        "items": folder_items
    }


# ==============================================================================
# User Search Endpoint (for share modal autocomplete)
# IMPORTANT: This must be defined BEFORE /{folder_id} routes to avoid matching
# ==============================================================================


@router.get("/users/search", response_model=list[UserSearchResult])
async def search_users_for_sharing(
    q: str = Query(..., min_length=1, description="Search query for username/email"),
    limit: int = Query(10, ge=1, le=50, description="Maximum results to return"),
    user: OIDCUser = Depends(idp.get_current_user(required_roles=["organization.write"])),
    org_context: OrganizationContext = Depends(get_user_organization),
):
    """Search users in organization for share modal autocomplete.

    Requires organization.write role for access (only users who can share folders).
    Returns users matching the search query within the current organization.

    The response includes has_write_permission to help the frontend determine
    if a user can be assigned the Writer role.
    """
    logger.info(
        "GET /folders/users/search - Searching users",
        extra={
            "user": org_context.username,
            "query": q,
            "limit": limit,
            "organization_id": org_context.organization_id
        }
    )

    # Search organization members via Keycloak Admin API
    members = await keycloak_admin_service.get_organization_members(
        organization_id=org_context.organization_id,
        first=0,
        max_results=limit,
        search=q
    )

    results = []
    for member in members:
        user_id = member.get("id")
        username = member.get("username", "")
        email = member.get("email", "")

        # Skip current user (can't share with self)
        if user_id == org_context.user_id:
            continue

        # Check if user has organization.write permission
        # This requires fetching user's roles from Keycloak
        user_roles = await keycloak_admin_service.get_user_realm_roles(user_id)
        role_names = {r.get("name") for r in user_roles}
        has_write_permission = "organization.write" in role_names

        results.append(UserSearchResult(
            user_id=user_id,
            username=username,
            email=email,
            has_write_permission=has_write_permission
        ))

    logger.debug(
        "User search results",
        extra={
            "query": q,
            "result_count": len(results)
        }
    )

    return results


# ==============================================================================
# Folder CRUD Endpoints
# ==============================================================================


@router.post("/", response_model=FolderResponse)
async def create_folder(
    folder: FolderCreate,
    user: OIDCUser = Depends(idp.get_current_user(required_roles=["organization.write"])),
    org_context: OrganizationContext = Depends(get_user_organization),
    db: AsyncSession = Depends(get_global_db)
):
    """Create a new folder in the organization.

    Requires organization.write role for access.
    The creating user becomes the folder owner.
    """
    logger.info(
        "POST /folders - Creating folder",
        extra={
            "user": org_context.username,
            "folder_name": folder.name,
            "organization_id": org_context.organization_id
        }
    )

    folder_obj = await FolderService.create_folder(
        db=db,
        organization_id=org_context.organization_id,
        owner_id=org_context.user_id,
        owner_username=org_context.username,
        folder_data=folder
    )

    logger.info(
        "Folder created successfully",
        extra={"folder_id": str(folder_obj.id), "folder_name": folder_obj.name}
    )

    return await _build_folder_response(
        db, folder_obj, org_context.user_id,
        org_id=org_context.organization_id,
        username=org_context.username,
        org_name=getattr(org_context, 'organization_name', ''),
        user_roles=user.realm_access.get('roles', []),
    )


@router.get("/", response_model=list[FolderResponse])
async def list_folders(
    archived: bool = Query(False),
    favorites: bool = Query(False),
    include_all: bool = Query(False, description="Include all org folders (managers only)"),
    user: OIDCUser = Depends(idp.get_current_user(required_roles=["organization.read"])),
    org_context: OrganizationContext = Depends(get_user_organization),
    db: AsyncSession = Depends(get_global_db)
):
    """List folders accessible to the current user (owned + shared).

    Requires organization.read role for access.
    Returns only folders the user owns or has been explicitly shared with.
    The is_favorite field is computed per-user.

    If include_all=true and user has organization.manage (or admin) role:
    - Returns ALL folders in organization (including private ones from other users)
    - Useful for team oversight by managers
    """
    logger.info(
        "GET /folders - Listing folders",
        extra={
            "user": org_context.username,
            "organization_id": org_context.organization_id,
            "archived": archived,
            "favorites": favorites,
            "include_all": include_all
        }
    )

    user_favorite_ids = await FolderService.get_user_favorite_folder_ids(
        db, org_context.user_id, org_context.organization_id
    )

    if include_all:
        user_roles = user.realm_access.get('roles', [])
        has_manager_permission = 'organization.manage' in user_roles or 'admin.organizations' in user_roles

        if not has_manager_permission:
            logger.warning(
                "Non-manager attempted to use include_all parameter",
                extra={
                    "user": org_context.username,
                    "user_id": org_context.user_id,
                    "roles": user_roles
                }
            )
            raise HTTPException(
                status_code=status.HTTP_403_FORBIDDEN,
                detail="Requires organization.manage permission to view all organization folders"
            )

        logger.info(
            "Manager viewing all organization folders",
            extra={
                "user": org_context.username,
                "organization_id": org_context.organization_id
            }
        )

        folders = await FolderService.list_all_org_folders(
            db=db,
            organization_id=org_context.organization_id,
            archived=archived,
            favorites_only=favorites,
            user_id=org_context.user_id
        )
    else:
        folders = await FolderService.list_folders(
            db=db,
            organization_id=org_context.organization_id,
            user_id=org_context.user_id,
            archived=archived,
            favorites_only=favorites,
            username=org_context.username
        )

    logger.info(
        "Found folders",
        extra={
            "count": len(folders),
            "organization_id": org_context.organization_id
        }
    )

    # Build response with access control fields
    user_roles = user.realm_access.get('roles', []) if user.realm_access else []
    response_folders = [
        await _build_folder_response(
            db, folder, org_context.user_id, user_favorite_ids,
            org_id=org_context.organization_id,
            username=org_context.username,
            org_name=getattr(org_context, 'organization_name', ''),
            user_roles=user_roles,
        )
        for folder in folders
    ]

    return response_folders


@router.get("/{folder_id}", response_model=FolderWithItemsResponse)
async def get_folder(
    folder_id: UUID,
    archived: bool = Query(False),
    user: OIDCUser = Depends(idp.get_current_user(required_roles=["organization.read"])),
    org_context: OrganizationContext = Depends(get_user_organization),
    db: AsyncSession = Depends(get_global_db)
):
    """Get a folder with its items.

    Requires organization.read role for access.
    Returns 404 if user has no access to the folder (owner or shared).
    """
    logger.info(
        f"GET /folders/{folder_id} - Getting folder",
        extra={
            "user": org_context.username,
            "folder_id": str(folder_id)
        }
    )

    # Check access - returns 404 for security (not 403)
    # Managers (organization.manage or admin.organizations) can access all folders
    user_roles = user.realm_access.get('roles', [])
    if not await FolderService.has_folder_access(
        db, folder_id, org_context.user_id, org_context.organization_id,
        username=org_context.username,
        user_roles=user_roles
    ):
        logger.warning(
            "Folder not found or no access",
            extra={
                "folder_id": str(folder_id),
                "user_id": org_context.user_id,
                "user_roles": user_roles
            }
        )
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail="Folder not found"
        )

    folder_data = await FolderService.get_folder_with_items(
        db=db,
        folder_id=folder_id,
        organization_id=org_context.organization_id,
        item_archived_filter=archived,
        user_id=org_context.user_id,
        username=org_context.username,
        org_name=getattr(org_context, 'organization_name', ''),
        roles=user.realm_access.get('roles', []),
    )

    if not folder_data:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail="Folder not found"
        )

    # Add access control fields
    folder = await FolderService.get_folder(db, folder_id, org_context.organization_id)
    share_role = await FolderService.get_user_folder_role(db, folder_id, org_context.user_id)

    folder_data['owner_id'] = folder.owner_id if folder else None
    folder_data['is_owner'] = share_role == "owner"
    folder_data['share_role'] = share_role
    folder_data['is_favorite'] = await FolderService.is_favorite(
        db, folder_id, org_context.user_id
    )

    logger.debug(
        "Returning folder data",
        extra={"folder_id": str(folder_id), "share_role": share_role}
    )
    return folder_data


@router.put("/{folder_id}", response_model=FolderResponse)
async def update_folder(
    folder_id: UUID,
    folder_update: FolderUpdate,
    user: OIDCUser = Depends(idp.get_current_user(required_roles=["organization.write"])),
    org_context: OrganizationContext = Depends(get_user_organization),
    db: AsyncSession = Depends(get_global_db)
):
    """Update a folder (owner only).

    Requires organization.write role AND folder ownership.
    Returns 403 if user is not the folder owner.
    """
    logger.info(
        f"PUT /folders/{folder_id} - Updating folder",
        extra={
            "user": org_context.username,
            "folder_id": str(folder_id)
        }
    )

    folder = await FolderService.get_folder(
        db=db,
        folder_id=folder_id,
        organization_id=org_context.organization_id
    )

    if not folder:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail="Folder not found"
        )

    # Check ownership - only owner can update folder metadata
    if not await FolderService.is_folder_owner(db, folder_id, org_context.user_id):
        logger.warning(
            "Non-owner attempted to update folder",
            extra={
                "folder_id": str(folder_id),
                "user_id": org_context.user_id,
                "owner_id": folder.owner_id
            }
        )
        raise HTTPException(
            status_code=status.HTTP_403_FORBIDDEN,
            detail="Only the folder owner can edit folder settings"
        )

    updated_folder = await FolderService.update_folder(
        db=db,
        folder=folder,
        folder_update=folder_update
    )

    return await _build_folder_response(
        db, updated_folder, org_context.user_id,
        org_id=org_context.organization_id,
        username=org_context.username,
        org_name=getattr(org_context, 'organization_name', ''),
        user_roles=user.realm_access.get('roles', []),
    )


@router.patch("/{folder_id}", response_model=FolderResponse)
async def patch_folder(
    folder_id: UUID,
    folder_update: FolderUpdate,
    user: OIDCUser = Depends(idp.get_current_user(required_roles=["organization.write"])),
    org_context: OrganizationContext = Depends(get_user_organization),
    db: AsyncSession = Depends(get_global_db)
):
    """Partially update a folder (owner only).

    Requires organization.write role AND folder ownership.
    Returns 403 if user is not the folder owner.
    Note: Favorites are managed via POST/DELETE /{folder_id}/favorite endpoints.
    """
    logger.info(
        f"PATCH /folders/{folder_id} - Patching folder",
        extra={
            "user": org_context.username,
            "folder_id": str(folder_id)
        }
    )

    folder = await FolderService.get_folder(
        db=db,
        folder_id=folder_id,
        organization_id=org_context.organization_id
    )

    if not folder:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail="Folder not found"
        )

    # Check ownership - only owner can update folder metadata
    if not await FolderService.is_folder_owner(db, folder_id, org_context.user_id):
        logger.warning(
            "Non-owner attempted to patch folder",
            extra={
                "folder_id": str(folder_id),
                "user_id": org_context.user_id
            }
        )
        raise HTTPException(
            status_code=status.HTTP_403_FORBIDDEN,
            detail="Only the folder owner can edit folder settings"
        )

    updated_folder = await FolderService.update_folder(
        db=db,
        folder=folder,
        folder_update=folder_update
    )

    return await _build_folder_response(
        db, updated_folder, org_context.user_id,
        org_id=org_context.organization_id,
        username=org_context.username,
        org_name=getattr(org_context, 'organization_name', ''),
        user_roles=user.realm_access.get('roles', []),
    )


@router.delete("/{folder_id}", response_model=FolderResponse)
async def delete_folder(
    folder_id: UUID,
    user: OIDCUser = Depends(idp.get_current_user(required_roles=["organization.write"])),
    org_context: OrganizationContext = Depends(get_user_organization),
    db: AsyncSession = Depends(get_global_db)
):
    """Soft delete a folder (owner only).

    Requires organization.write role AND folder ownership.
    Returns 403 if user is not the folder owner.
    """
    logger.info(
        f"DELETE /folders/{folder_id} - Deleting folder",
        extra={
            "user": org_context.username,
            "folder_id": str(folder_id)
        }
    )

    folder = await FolderService.get_folder(
        db=db,
        folder_id=folder_id,
        organization_id=org_context.organization_id
    )

    if not folder:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail="Folder not found"
        )

    # Check ownership - only owner can delete folder
    if not await FolderService.is_folder_owner(db, folder_id, org_context.user_id):
        logger.warning(
            "Non-owner attempted to delete folder",
            extra={
                "folder_id": str(folder_id),
                "user_id": org_context.user_id
            }
        )
        raise HTTPException(
            status_code=status.HTTP_403_FORBIDDEN,
            detail="Only the folder owner can delete the folder"
        )

    deleted_folder = await FolderService.soft_delete_folder(db=db, folder=folder)

    return await _build_folder_response(
        db, deleted_folder, org_context.user_id,
        org_id=org_context.organization_id,
        username=org_context.username,
        org_name=getattr(org_context, 'organization_name', ''),
        user_roles=user.realm_access.get('roles', []),
    )


@router.post("/{folder_id}/restore", response_model=FolderResponse)
async def restore_folder(
    folder_id: UUID,
    user: OIDCUser = Depends(idp.get_current_user(required_roles=["organization.write"])),
    org_context: OrganizationContext = Depends(get_user_organization),
    db: AsyncSession = Depends(get_global_db)
):
    """Restore a soft-deleted folder (owner only).

    Requires organization.write role AND folder ownership.
    Returns 403 if user is not the folder owner.
    """
    logger.info(
        f"POST /folders/{folder_id}/restore - Restoring folder",
        extra={
            "user": org_context.username,
            "folder_id": str(folder_id)
        }
    )

    folder = await FolderService.get_folder(
        db=db,
        folder_id=folder_id,
        organization_id=org_context.organization_id,
        include_deleted=True
    )

    if not folder:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail="Folder not found"
        )

    # Check ownership - only owner can restore folder
    if not await FolderService.is_folder_owner(db, folder_id, org_context.user_id):
        logger.warning(
            "Non-owner attempted to restore folder",
            extra={
                "folder_id": str(folder_id),
                "user_id": org_context.user_id
            }
        )
        raise HTTPException(
            status_code=status.HTTP_403_FORBIDDEN,
            detail="Only the folder owner can restore the folder"
        )

    if not folder.is_deleted:
        raise HTTPException(
            status_code=status.HTTP_400_BAD_REQUEST,
            detail="Folder is not deleted"
        )

    restored_folder = await FolderService.restore_folder(db=db, folder=folder)

    return await _build_folder_response(
        db, restored_folder, org_context.user_id,
        org_id=org_context.organization_id,
        username=org_context.username,
        org_name=getattr(org_context, 'organization_name', ''),
        user_roles=user.realm_access.get('roles', []),
    )


# ==============================================================================
# Folder Sharing Endpoints
# ==============================================================================


@router.post("/{folder_id}/shares", response_model=FolderShareResponse, status_code=status.HTTP_201_CREATED)
async def create_folder_share(
    folder_id: UUID,
    share_data: FolderShareCreate,
    user: OIDCUser = Depends(idp.get_current_user(required_roles=["organization.write"])),
    org_context: OrganizationContext = Depends(get_user_organization),
    db: AsyncSession = Depends(get_global_db)
):
    """Share a folder with a user (owner only).

    Requires organization.write role AND folder ownership.
    Returns 403 if user is not the folder owner.
    """
    logger.info(
        f"POST /folders/{folder_id}/shares - Creating share",
        extra={
            "user": org_context.username,
            "folder_id": str(folder_id),
            "share_user_id": share_data.user_id,
            "share_role": share_data.role.value
        }
    )

    # Check folder exists
    folder = await FolderService.get_folder(
        db=db,
        folder_id=folder_id,
        organization_id=org_context.organization_id
    )

    if not folder:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail="Folder not found"
        )

    # Check ownership - only owner can manage sharing
    if not await FolderService.is_folder_owner(db, folder_id, org_context.user_id):
        logger.warning(
            "Non-owner attempted to share folder",
            extra={
                "folder_id": str(folder_id),
                "user_id": org_context.user_id
            }
        )
        raise HTTPException(
            status_code=status.HTTP_403_FORBIDDEN,
            detail="Only the folder owner can manage sharing"
        )

    # Prevent sharing with self
    if share_data.user_id == org_context.user_id:
        raise HTTPException(
            status_code=status.HTTP_400_BAD_REQUEST,
            detail="Cannot share folder with yourself"
        )

    # Map Pydantic enum to SQLAlchemy enum
    role = ShareRole.writer if share_data.role.value == "writer" else ShareRole.reader

    try:
        share = await FolderService.share_folder(
            db=db,
            folder_id=folder_id,
            user_id=share_data.user_id,
            user_username=share_data.user_username,
            role=role
        )
    except ValueError as e:
        raise HTTPException(
            status_code=status.HTTP_400_BAD_REQUEST,
            detail=str(e)
        )

    logger.info(
        "Folder share created",
        extra={
            "folder_id": str(folder_id),
            "share_user_id": share_data.user_id,
            "share_id": str(share.id)
        }
    )

    return share


@router.get("/{folder_id}/shares", response_model=list[FolderShareResponse])
async def get_folder_shares(
    folder_id: UUID,
    user: OIDCUser = Depends(idp.get_current_user(required_roles=["organization.read"])),
    org_context: OrganizationContext = Depends(get_user_organization),
    db: AsyncSession = Depends(get_global_db)
):
    """List all shares for a folder (owner only).

    Requires organization.read role AND folder ownership.
    Returns 403 if user is not the folder owner.

    Each share includes has_write_permission to indicate if the user
    can be assigned the Writer role.
    """
    logger.info(
        f"GET /folders/{folder_id}/shares - Listing shares",
        extra={
            "user": org_context.username,
            "folder_id": str(folder_id)
        }
    )

    # Check folder exists
    folder = await FolderService.get_folder(
        db=db,
        folder_id=folder_id,
        organization_id=org_context.organization_id
    )

    if not folder:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail="Folder not found"
        )

    # Check ownership - only owner can view shares
    if not await FolderService.is_folder_owner(db, folder_id, org_context.user_id):
        logger.warning(
            "Non-owner attempted to view folder shares",
            extra={
                "folder_id": str(folder_id),
                "user_id": org_context.user_id
            }
        )
        raise HTTPException(
            status_code=status.HTTP_403_FORBIDDEN,
            detail="Only the folder owner can view sharing settings"
        )

    shares = await FolderService.get_folder_shares(db, folder_id)

    # Enrich shares with has_write_permission from Keycloak
    enriched_shares = []
    for share in shares:
        # Fetch user's roles from Keycloak
        user_roles = await keycloak_admin_service.get_user_realm_roles(share.user_id)
        role_names = {r.get("name") for r in user_roles}
        has_write_permission = "organization.write" in role_names

        enriched_shares.append(FolderShareResponse(
            id=share.id,
            folder_id=share.folder_id,
            user_id=share.user_id,
            user_username=share.user_username,
            role=share.role,
            created_at=share.created_at,
            has_write_permission=has_write_permission
        ))

    logger.debug(
        "Returning folder shares",
        extra={
            "folder_id": str(folder_id),
            "share_count": len(enriched_shares)
        }
    )

    return enriched_shares


@router.delete("/{folder_id}/shares/{share_user_id}", status_code=status.HTTP_200_OK)
async def delete_folder_share(
    folder_id: UUID,
    share_user_id: str,
    user: OIDCUser = Depends(idp.get_current_user(required_roles=["organization.write"])),
    org_context: OrganizationContext = Depends(get_user_organization),
    db: AsyncSession = Depends(get_global_db)
):
    """Remove a user's access to a folder (owner only).

    Requires organization.write role AND folder ownership.
    Returns 403 if user is not the folder owner.
    """
    logger.info(
        f"DELETE /folders/{folder_id}/shares/{share_user_id} - Removing share",
        extra={
            "user": org_context.username,
            "folder_id": str(folder_id),
            "share_user_id": share_user_id
        }
    )

    # Check folder exists
    folder = await FolderService.get_folder(
        db=db,
        folder_id=folder_id,
        organization_id=org_context.organization_id
    )

    if not folder:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail="Folder not found"
        )

    # Check ownership - only owner can manage sharing
    if not await FolderService.is_folder_owner(db, folder_id, org_context.user_id):
        logger.warning(
            "Non-owner attempted to remove folder share",
            extra={
                "folder_id": str(folder_id),
                "user_id": org_context.user_id
            }
        )
        raise HTTPException(
            status_code=status.HTTP_403_FORBIDDEN,
            detail="Only the folder owner can manage sharing"
        )

    removed = await FolderService.unshare_folder(db, folder_id, share_user_id)

    if not removed:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail="Share not found"
        )

    logger.info(
        "Folder share removed",
        extra={
            "folder_id": str(folder_id),
            "share_user_id": share_user_id
        }
    )

    return {"message": "Share removed successfully"}


@router.patch("/{folder_id}/shares/{share_user_id}", response_model=FolderShareResponse)
async def update_folder_share(
    folder_id: UUID,
    share_user_id: str,
    share_update: FolderShareUpdate,
    user: OIDCUser = Depends(idp.get_current_user(required_roles=["organization.write"])),
    org_context: OrganizationContext = Depends(get_user_organization),
    db: AsyncSession = Depends(get_global_db)
):
    """Update a user's share role (owner only).

    Requires organization.write role AND folder ownership.
    Returns 403 if user is not the folder owner.
    """
    logger.info(
        f"PATCH /folders/{folder_id}/shares/{share_user_id} - Updating share role",
        extra={
            "user": org_context.username,
            "folder_id": str(folder_id),
            "share_user_id": share_user_id,
            "new_role": share_update.role.value
        }
    )

    # Check folder exists
    folder = await FolderService.get_folder(
        db=db,
        folder_id=folder_id,
        organization_id=org_context.organization_id
    )

    if not folder:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail="Folder not found"
        )

    # Check ownership - only owner can manage sharing
    if not await FolderService.is_folder_owner(db, folder_id, org_context.user_id):
        logger.warning(
            "Non-owner attempted to update folder share",
            extra={
                "folder_id": str(folder_id),
                "user_id": org_context.user_id
            }
        )
        raise HTTPException(
            status_code=status.HTTP_403_FORBIDDEN,
            detail="Only the folder owner can manage sharing"
        )

    # Map Pydantic enum to SQLAlchemy enum
    role = ShareRole.writer if share_update.role.value == "writer" else ShareRole.reader

    share = await FolderService.update_share_role(db, folder_id, share_user_id, role)

    if not share:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail="Share not found"
        )

    logger.info(
        "Folder share updated",
        extra={
            "folder_id": str(folder_id),
            "share_user_id": share_user_id,
            "new_role": share_update.role.value
        }
    )

    return share


# ==============================================================================
# User Favorites Endpoints
# ==============================================================================


@router.post("/{folder_id}/favorite", status_code=status.HTTP_201_CREATED)
async def add_folder_favorite(
    folder_id: UUID,
    user: OIDCUser = Depends(idp.get_current_user(required_roles=["organization.read"])),
    org_context: OrganizationContext = Depends(get_user_organization),
    db: AsyncSession = Depends(get_global_db)
):
    """Add a folder to the current user's favorites.

    Requires organization.read role for access.
    User must have access to the folder (owner or shared).
    """
    logger.info(
        f"POST /folders/{folder_id}/favorite - Adding favorite",
        extra={
            "user": org_context.username,
            "folder_id": str(folder_id)
        }
    )

    # Check access - user must have access to favorite a folder
    if not await FolderService.has_folder_access(
        db, folder_id, org_context.user_id, org_context.organization_id,
        username=org_context.username
    ):
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail="Folder not found"
        )

    added = await FolderService.add_favorite(db, folder_id, org_context.user_id)
    if not added:
        raise HTTPException(
            status_code=status.HTTP_400_BAD_REQUEST,
            detail="Folder already in favorites"
        )

    logger.info(
        "Folder added to favorites",
        extra={
            "folder_id": str(folder_id),
            "user": org_context.username
        }
    )

    return {"message": "Folder added to favorites", "is_favorite": True}


@router.delete("/{folder_id}/favorite", status_code=status.HTTP_200_OK)
async def remove_folder_favorite(
    folder_id: UUID,
    user: OIDCUser = Depends(idp.get_current_user(required_roles=["organization.read"])),
    org_context: OrganizationContext = Depends(get_user_organization),
    db: AsyncSession = Depends(get_global_db)
):
    """Remove a folder from the current user's favorites.

    Requires organization.read role for access.
    User must have access to the folder (owner or shared).
    """
    logger.info(
        f"DELETE /folders/{folder_id}/favorite - Removing favorite",
        extra={
            "user": org_context.username,
            "folder_id": str(folder_id)
        }
    )

    # Check access - user must have access to unfavorite a folder
    if not await FolderService.has_folder_access(
        db, folder_id, org_context.user_id, org_context.organization_id,
        username=org_context.username
    ):
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail="Folder not found"
        )

    removed = await FolderService.remove_favorite(db, folder_id, org_context.user_id)
    if not removed:
        raise HTTPException(
            status_code=status.HTTP_400_BAD_REQUEST,
            detail="Folder not in favorites"
        )

    logger.info(
        "Folder removed from favorites",
        extra={
            "folder_id": str(folder_id),
            "user": org_context.username
        }
    )

    return {"message": "Folder removed from favorites", "is_favorite": False}


# ==============================================================================
# Folder Items Endpoints
# ==============================================================================


@router.post("/{folder_id}/items", response_model=FolderItemResponse)
async def add_item_to_folder(
    folder_id: UUID,
    item: FolderItemAdd,
    user: OIDCUser = Depends(idp.get_current_user(required_roles=["company.create"])),
    org_context: OrganizationContext = Depends(get_user_organization),
    db: AsyncSession = Depends(get_global_db)
):
    """Add an item to a folder.

    Requires company.create role AND (owner OR writer role for folder).
    Readers cannot add items even with company.create permission.
    """
    logger.info(
        f"POST /folders/{folder_id}/items - Adding item",
        extra={
            "user": org_context.username,
            "folder_id": str(folder_id),
            "item_id": item.item_id,
            "item_type": item.item_type
        }
    )

    # Check folder exists and get user's role
    folder = await FolderService.get_folder(
        db=db,
        folder_id=folder_id,
        organization_id=org_context.organization_id
    )

    if not folder:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail="Folder not found"
        )

    # Check role-based access - must be owner or writer
    role = await FolderService.get_user_folder_role(db, folder_id, org_context.user_id)

    if role not in ("owner", "writer"):
        if role == "reader":
            logger.warning(
                "Reader attempted to add item to folder",
                extra={
                    "folder_id": str(folder_id),
                    "user_id": org_context.user_id
                }
            )
            raise HTTPException(
                status_code=status.HTTP_403_FORBIDDEN,
                detail="Readers cannot add items to folders"
            )
        else:
            raise HTTPException(
                status_code=status.HTTP_404_NOT_FOUND,
                detail="Folder not found"
            )

    folder_item = await FolderService.add_item_to_folder(
        db=db,
        folder_id=folder_id,
        item_id=item.item_id,
        item_type=item.item_type,
        owner=org_context.username,
        position=item.position
    )

    logger.info(
        "Item added to folder",
        extra={
            "folder_id": str(folder_id),
            "folder_item_id": str(folder_item.id)
        }
    )

    return folder_item


@router.delete("/{folder_id}/items/{item_id}")
async def remove_item_from_folder(
    folder_id: UUID,
    item_id: UUID,
    item_type: str = Query(..., pattern="^(company|contact|document)$"),
    user: OIDCUser = Depends(idp.get_current_user(required_roles=["organization.write"])),
    org_context: OrganizationContext = Depends(get_user_organization),
    db: AsyncSession = Depends(get_global_db)
):
    """Remove an item from a folder (owner only).

    Requires organization.write role AND folder ownership.
    Writers cannot delete items from folders.
    """
    logger.info(
        f"DELETE /folders/{folder_id}/items/{item_id} - Removing item",
        extra={
            "user": org_context.username,
            "folder_id": str(folder_id),
            "item_id": str(item_id),
            "item_type": item_type
        }
    )

    folder = await FolderService.get_folder(
        db=db,
        folder_id=folder_id,
        organization_id=org_context.organization_id
    )

    if not folder:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail="Folder not found"
        )

    # Check ownership - only owner can delete items
    if not await FolderService.is_folder_owner(db, folder_id, org_context.user_id):
        logger.warning(
            "Non-owner attempted to remove item from folder",
            extra={
                "folder_id": str(folder_id),
                "user_id": org_context.user_id
            }
        )
        raise HTTPException(
            status_code=status.HTTP_403_FORBIDDEN,
            detail="Only the folder owner can remove items"
        )

    removed = await FolderService.remove_item_from_folder(
        db=db,
        folder_id=folder_id,
        item_id=str(item_id),
        item_type=item_type
    )

    if not removed:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail="Item not found in folder"
        )

    logger.info(
        "Item removed from folder",
        extra={
            "folder_id": str(folder_id),
            "item_id": str(item_id)
        }
    )

    return {"message": "Item removed from folder"}


@router.patch("/{folder_id}/items/{item_id}", response_model=FolderItemMoveResponse)
async def update_folder_item(
    folder_id: UUID,
    item_id: str,
    item_type: str,
    move_data: FolderItemMove,
    user: OIDCUser = Depends(idp.get_current_user(required_roles=["organization.write"])),
    org_context: OrganizationContext = Depends(get_user_organization),
    db: AsyncSession = Depends(get_global_db)
):
    """Update an item's folder (move item to a different folder).

    This RESTful endpoint updates the folder_id attribute of an item,
    effectively moving it from the current folder to the destination folder.

    Requires organization.write role AND write access (owner or writer) to both folders.
    """
    logger.info(
        f"PATCH /folders/{folder_id}/items/{item_id} - Moving item to new folder",
        extra={
            "user": org_context.username,
            "current_folder_id": str(folder_id),
            "destination_folder_id": str(move_data.folder_id),
            "item_id": item_id,
            "item_type": item_type
        }
    )

    # Validate current folder exists and user has access
    current_folder = await FolderService.get_folder(
        db=db,
        folder_id=folder_id,
        organization_id=org_context.organization_id
    )

    if not current_folder:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail="Current folder not found"
        )

    # Check write access to current folder (owner or writer)
    current_role = await FolderService.get_user_folder_role(
        db, folder_id, org_context.user_id
    )
    if not current_role or current_role not in ['owner', 'writer']:
        logger.warning(
            "User lacks write access to current folder",
            extra={
                "folder_id": str(folder_id),
                "user_id": org_context.user_id,
                "role": current_role
            }
        )
        raise HTTPException(
            status_code=status.HTTP_403_FORBIDDEN,
            detail="You need write access to the current folder to move items"
        )

    # Validate destination folder exists and user has access
    destination_folder = await FolderService.get_folder(
        db=db,
        folder_id=move_data.folder_id,
        organization_id=org_context.organization_id
    )

    if not destination_folder:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail="Destination folder not found"
        )

    # Check write access to destination folder (owner or writer)
    dest_role = await FolderService.get_user_folder_role(
        db, move_data.folder_id, org_context.user_id
    )
    if not dest_role or dest_role not in ['owner', 'writer']:
        logger.warning(
            "User lacks write access to destination folder",
            extra={
                "folder_id": str(move_data.folder_id),
                "user_id": org_context.user_id,
                "role": dest_role
            }
        )
        raise HTTPException(
            status_code=status.HTTP_403_FORBIDDEN,
            detail="You need write access to the destination folder to add items"
        )

    # Move the item
    moved_item = await FolderService.update_item_folder(
        db=db,
        folder_id=folder_id,
        item_id=item_id,
        item_type=item_type,
        destination_folder_id=move_data.folder_id,
        organization_id=org_context.organization_id
    )

    if not moved_item:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail="Item not found in current folder"
        )

    logger.info(
        "Item moved successfully",
        extra={
            "current_folder_id": str(folder_id),
            "destination_folder_id": str(move_data.folder_id),
            "item_id": item_id
        }
    )

    return FolderItemMoveResponse(
        message="Item moved successfully",
        item=moved_item
    )
