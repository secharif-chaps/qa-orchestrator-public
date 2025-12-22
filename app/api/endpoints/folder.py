"""Folder API endpoints with sharing and access control.

This module provides REST API endpoints for:
- Folder CRUD operations (with owner-only write access)
- Folder sharing management (owner-only)
- Folder items management (role-based access)
- User favorites
"""

from typing import List
from uuid import UUID
from fastapi import APIRouter, Depends, HTTPException, status, Query
from fastapi_keycloak import OIDCUser
from sqlalchemy.orm import Session

from app.database import get_db
from app.core.keycloak import idp
from app.core.logging_config import get_logger
from app.schemas.folder import (
    FolderCreate,
    FolderUpdate,
    FolderResponse,
    FolderWithItemsResponse,
    FolderItemAdd,
    FolderItemResponse,
    FolderShareCreate,
    FolderShareUpdate,
    FolderShareResponse,
    UserSearchResult,
)
from app.models.folder import ShareRole
from app.services.folder import FolderService
from app.services.keycloak_admin import keycloak_admin_service
from app.core.organization import get_user_organization, OrganizationContext


router = APIRouter()
logger = get_logger(__name__)


def _build_folder_response(
    db: Session,
    folder,
    user_id: str,
    user_favorite_ids: set = None
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

    Returns:
        Dict ready for FolderResponse serialization
    """
    # Determine user's role for this folder
    share_role = FolderService.get_user_folder_role(db, folder.id, user_id)
    is_owner = share_role == "owner"

    # Determine favorite status
    if user_favorite_ids is not None:
        is_favorite = folder.id in user_favorite_ids
    else:
        is_favorite = FolderService.is_favorite(db, folder.id, user_id)

    # Get folder items
    folder_items = FolderService._get_folder_items_summary(db, folder.id)

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


@router.get("/users/search", response_model=List[UserSearchResult])
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
def create_folder(
    folder: FolderCreate,
    user: OIDCUser = Depends(idp.get_current_user(required_roles=["organization.write"])),
    org_context: OrganizationContext = Depends(get_user_organization),
    db: Session = Depends(get_db)
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

    folder_obj = FolderService.create_folder(
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

    return _build_folder_response(db, folder_obj, org_context.user_id)


@router.get("/", response_model=List[FolderResponse])
def list_folders(
    archived: bool = Query(False),
    favorites: bool = Query(False),
    include_all: bool = Query(False, description="Include all org folders (managers only)"),
    user: OIDCUser = Depends(idp.get_current_user(required_roles=["organization.read"])),
    org_context: OrganizationContext = Depends(get_user_organization),
    db: Session = Depends(get_db)
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

    user_favorite_ids = FolderService.get_user_favorite_folder_ids(
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

        folders = FolderService.list_all_org_folders(
            db=db,
            organization_id=org_context.organization_id,
            archived=archived,
            favorites_only=favorites,
            user_id=org_context.user_id
        )
    else:
        folders = FolderService.list_folders(
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
    response_folders = [
        _build_folder_response(db, folder, org_context.user_id, user_favorite_ids)
        for folder in folders
    ]

    return response_folders


@router.get("/{folder_id}", response_model=FolderWithItemsResponse)
def get_folder(
    folder_id: UUID,
    archived: bool = Query(False),
    user: OIDCUser = Depends(idp.get_current_user(required_roles=["organization.read"])),
    org_context: OrganizationContext = Depends(get_user_organization),
    db: Session = Depends(get_db)
):
    """Get a folder with its items.

    Requires organization.read role for access.
    Returns 404 if user has no access to the folder (owner or shared).
    """
    logger.info(
        "GET /folders/{folder_id} - Getting folder",
        extra={
            "user": org_context.username,
            "folder_id": str(folder_id)
        }
    )

    # Check access - returns 404 for security (not 403)
    # Managers (organization.manage or admin.organizations) can access all folders
    user_roles = user.realm_access.get('roles', [])
    if not FolderService.has_folder_access(
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

    folder_data = FolderService.get_folder_with_items(
        db=db,
        folder_id=folder_id,
        organization_id=org_context.organization_id,
        item_archived_filter=archived
    )

    if not folder_data:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail="Folder not found"
        )

    # Add access control fields
    folder = FolderService.get_folder(db, folder_id, org_context.organization_id)
    share_role = FolderService.get_user_folder_role(db, folder_id, org_context.user_id)

    folder_data['owner_id'] = folder.owner_id if folder else None
    folder_data['is_owner'] = share_role == "owner"
    folder_data['share_role'] = share_role
    folder_data['is_favorite'] = FolderService.is_favorite(
        db, folder_id, org_context.user_id
    )

    logger.debug(
        "Returning folder data",
        extra={"folder_id": str(folder_id), "share_role": share_role}
    )
    return folder_data


@router.put("/{folder_id}", response_model=FolderResponse)
def update_folder(
    folder_id: UUID,
    folder_update: FolderUpdate,
    user: OIDCUser = Depends(idp.get_current_user(required_roles=["organization.write"])),
    org_context: OrganizationContext = Depends(get_user_organization),
    db: Session = Depends(get_db)
):
    """Update a folder (owner only).

    Requires organization.write role AND folder ownership.
    Returns 403 if user is not the folder owner.
    """
    logger.info(
        "PUT /folders/{folder_id} - Updating folder",
        extra={
            "user": org_context.username,
            "folder_id": str(folder_id)
        }
    )

    folder = FolderService.get_folder(
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
    if not FolderService.is_folder_owner(db, folder_id, org_context.user_id):
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

    updated_folder = FolderService.update_folder(
        db=db,
        folder=folder,
        folder_update=folder_update
    )

    return _build_folder_response(db, updated_folder, org_context.user_id)


@router.patch("/{folder_id}", response_model=FolderResponse)
def patch_folder(
    folder_id: UUID,
    folder_update: FolderUpdate,
    user: OIDCUser = Depends(idp.get_current_user(required_roles=["organization.write"])),
    org_context: OrganizationContext = Depends(get_user_organization),
    db: Session = Depends(get_db)
):
    """Partially update a folder (owner only).

    Requires organization.write role AND folder ownership.
    Returns 403 if user is not the folder owner.
    Note: Favorites are managed via POST/DELETE /{folder_id}/favorite endpoints.
    """
    logger.info(
        "PATCH /folders/{folder_id} - Patching folder",
        extra={
            "user": org_context.username,
            "folder_id": str(folder_id)
        }
    )

    folder = FolderService.get_folder(
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
    if not FolderService.is_folder_owner(db, folder_id, org_context.user_id):
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

    updated_folder = FolderService.update_folder(
        db=db,
        folder=folder,
        folder_update=folder_update
    )

    return _build_folder_response(db, updated_folder, org_context.user_id)


@router.delete("/{folder_id}", response_model=FolderResponse)
def delete_folder(
    folder_id: UUID,
    user: OIDCUser = Depends(idp.get_current_user(required_roles=["organization.write"])),
    org_context: OrganizationContext = Depends(get_user_organization),
    db: Session = Depends(get_db)
):
    """Soft delete a folder (owner only).

    Requires organization.write role AND folder ownership.
    Returns 403 if user is not the folder owner.
    """
    logger.info(
        "DELETE /folders/{folder_id} - Deleting folder",
        extra={
            "user": org_context.username,
            "folder_id": str(folder_id)
        }
    )

    folder = FolderService.get_folder(
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
    if not FolderService.is_folder_owner(db, folder_id, org_context.user_id):
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

    deleted_folder = FolderService.soft_delete_folder(db=db, folder=folder)

    return _build_folder_response(db, deleted_folder, org_context.user_id)


@router.post("/{folder_id}/restore", response_model=FolderResponse)
def restore_folder(
    folder_id: UUID,
    user: OIDCUser = Depends(idp.get_current_user(required_roles=["organization.write"])),
    org_context: OrganizationContext = Depends(get_user_organization),
    db: Session = Depends(get_db)
):
    """Restore a soft-deleted folder (owner only).

    Requires organization.write role AND folder ownership.
    Returns 403 if user is not the folder owner.
    """
    logger.info(
        "POST /folders/{folder_id}/restore - Restoring folder",
        extra={
            "user": org_context.username,
            "folder_id": str(folder_id)
        }
    )

    folder = FolderService.get_folder(
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
    if not FolderService.is_folder_owner(db, folder_id, org_context.user_id):
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

    restored_folder = FolderService.restore_folder(db=db, folder=folder)

    return _build_folder_response(db, restored_folder, org_context.user_id)


# ==============================================================================
# Folder Sharing Endpoints
# ==============================================================================


@router.post("/{folder_id}/shares", response_model=FolderShareResponse, status_code=status.HTTP_201_CREATED)
def create_folder_share(
    folder_id: UUID,
    share_data: FolderShareCreate,
    user: OIDCUser = Depends(idp.get_current_user(required_roles=["organization.write"])),
    org_context: OrganizationContext = Depends(get_user_organization),
    db: Session = Depends(get_db)
):
    """Share a folder with a user (owner only).

    Requires organization.write role AND folder ownership.
    Returns 403 if user is not the folder owner.
    """
    logger.info(
        "POST /folders/{folder_id}/shares - Creating share",
        extra={
            "user": org_context.username,
            "folder_id": str(folder_id),
            "share_user_id": share_data.user_id,
            "share_role": share_data.role.value
        }
    )

    # Check folder exists
    folder = FolderService.get_folder(
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
    if not FolderService.is_folder_owner(db, folder_id, org_context.user_id):
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
        share = FolderService.share_folder(
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


@router.get("/{folder_id}/shares", response_model=List[FolderShareResponse])
async def get_folder_shares(
    folder_id: UUID,
    user: OIDCUser = Depends(idp.get_current_user(required_roles=["organization.read"])),
    org_context: OrganizationContext = Depends(get_user_organization),
    db: Session = Depends(get_db)
):
    """List all shares for a folder (owner only).

    Requires organization.read role AND folder ownership.
    Returns 403 if user is not the folder owner.

    Each share includes has_write_permission to indicate if the user
    can be assigned the Writer role.
    """
    logger.info(
        "GET /folders/{folder_id}/shares - Listing shares",
        extra={
            "user": org_context.username,
            "folder_id": str(folder_id)
        }
    )

    # Check folder exists
    folder = FolderService.get_folder(
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
    if not FolderService.is_folder_owner(db, folder_id, org_context.user_id):
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

    shares = FolderService.get_folder_shares(db, folder_id)

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
def delete_folder_share(
    folder_id: UUID,
    share_user_id: str,
    user: OIDCUser = Depends(idp.get_current_user(required_roles=["organization.write"])),
    org_context: OrganizationContext = Depends(get_user_organization),
    db: Session = Depends(get_db)
):
    """Remove a user's access to a folder (owner only).

    Requires organization.write role AND folder ownership.
    Returns 403 if user is not the folder owner.
    """
    logger.info(
        "DELETE /folders/{folder_id}/shares/{user_id} - Removing share",
        extra={
            "user": org_context.username,
            "folder_id": str(folder_id),
            "share_user_id": share_user_id
        }
    )

    # Check folder exists
    folder = FolderService.get_folder(
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
    if not FolderService.is_folder_owner(db, folder_id, org_context.user_id):
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

    removed = FolderService.unshare_folder(db, folder_id, share_user_id)

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
def update_folder_share(
    folder_id: UUID,
    share_user_id: str,
    share_update: FolderShareUpdate,
    user: OIDCUser = Depends(idp.get_current_user(required_roles=["organization.write"])),
    org_context: OrganizationContext = Depends(get_user_organization),
    db: Session = Depends(get_db)
):
    """Update a user's share role (owner only).

    Requires organization.write role AND folder ownership.
    Returns 403 if user is not the folder owner.
    """
    logger.info(
        "PATCH /folders/{folder_id}/shares/{user_id} - Updating share role",
        extra={
            "user": org_context.username,
            "folder_id": str(folder_id),
            "share_user_id": share_user_id,
            "new_role": share_update.role.value
        }
    )

    # Check folder exists
    folder = FolderService.get_folder(
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
    if not FolderService.is_folder_owner(db, folder_id, org_context.user_id):
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

    share = FolderService.update_share_role(db, folder_id, share_user_id, role)

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
def add_folder_favorite(
    folder_id: UUID,
    user: OIDCUser = Depends(idp.get_current_user(required_roles=["organization.read"])),
    org_context: OrganizationContext = Depends(get_user_organization),
    db: Session = Depends(get_db)
):
    """Add a folder to the current user's favorites.

    Requires organization.read role for access.
    User must have access to the folder (owner or shared).
    """
    logger.info(
        "POST /folders/{folder_id}/favorite - Adding favorite",
        extra={
            "user": org_context.username,
            "folder_id": str(folder_id)
        }
    )

    # Check access - user must have access to favorite a folder
    if not FolderService.has_folder_access(
        db, folder_id, org_context.user_id, org_context.organization_id,
        username=org_context.username
    ):
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail="Folder not found"
        )

    added = FolderService.add_favorite(db, folder_id, org_context.user_id)
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
def remove_folder_favorite(
    folder_id: UUID,
    user: OIDCUser = Depends(idp.get_current_user(required_roles=["organization.read"])),
    org_context: OrganizationContext = Depends(get_user_organization),
    db: Session = Depends(get_db)
):
    """Remove a folder from the current user's favorites.

    Requires organization.read role for access.
    User must have access to the folder (owner or shared).
    """
    logger.info(
        "DELETE /folders/{folder_id}/favorite - Removing favorite",
        extra={
            "user": org_context.username,
            "folder_id": str(folder_id)
        }
    )

    # Check access - user must have access to unfavorite a folder
    if not FolderService.has_folder_access(
        db, folder_id, org_context.user_id, org_context.organization_id,
        username=org_context.username
    ):
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail="Folder not found"
        )

    removed = FolderService.remove_favorite(db, folder_id, org_context.user_id)
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
def add_item_to_folder(
    folder_id: UUID,
    item: FolderItemAdd,
    user: OIDCUser = Depends(idp.get_current_user(required_roles=["company.create"])),
    org_context: OrganizationContext = Depends(get_user_organization),
    db: Session = Depends(get_db)
):
    """Add an item to a folder.

    Requires company.create role AND (owner OR writer role for folder).
    Readers cannot add items even with company.create permission.
    """
    logger.info(
        "POST /folders/{folder_id}/items - Adding item",
        extra={
            "user": org_context.username,
            "folder_id": str(folder_id),
            "item_id": item.item_id,
            "item_type": item.item_type
        }
    )

    # Check folder exists and get user's role
    folder = FolderService.get_folder(
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
    role = FolderService.get_user_folder_role(db, folder_id, org_context.user_id)

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

    folder_item = FolderService.add_item_to_folder(
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
def remove_item_from_folder(
    folder_id: UUID,
    item_id: UUID,
    item_type: str = Query(..., pattern="^(company|contact|document)$"),
    user: OIDCUser = Depends(idp.get_current_user(required_roles=["organization.write"])),
    org_context: OrganizationContext = Depends(get_user_organization),
    db: Session = Depends(get_db)
):
    """Remove an item from a folder (owner only).

    Requires organization.write role AND folder ownership.
    Writers cannot delete items from folders.
    """
    logger.info(
        "DELETE /folders/{folder_id}/items/{item_id} - Removing item",
        extra={
            "user": org_context.username,
            "folder_id": str(folder_id),
            "item_id": str(item_id),
            "item_type": item_type
        }
    )

    folder = FolderService.get_folder(
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
    if not FolderService.is_folder_owner(db, folder_id, org_context.user_id):
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

    removed = FolderService.remove_item_from_folder(
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
